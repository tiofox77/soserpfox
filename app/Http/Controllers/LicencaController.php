<?php

namespace App\Http\Controllers;

use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseState;
use App\Services\Licensing\MachineFingerprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Ecrã de activação / estado da licença (build offline). É a única porta que
 * responde com o sistema bloqueado — por isso o middleware da licença deixa
 * `licenca*` sempre passar.
 */
class LicencaController extends Controller
{
    public function __construct(private LicenseManager $licencas)
    {
    }

    public function index(Request $request)
    {
        $estado = $this->licencas->estado(true);

        // Licença boa? Então este ecrã já cumpriu o seu papel e não deve ficar
        // no caminho. Segue-se para onde falta trabalho: criar a empresa, ou
        // entrar. Quem quiser mesmo gerir a licença chega aqui por /licenca?ver=1
        // (e o banner de aviso continua a apontar para cá).
        if (!$estado->bloqueiaTudo() && !$request->boolean('ver')) {
            // Pedido já cumprido: apagar para não ficar a oferecer "verificar
            // aprovação" de uma licença que já está instalada.
            $this->esquecerPedido();

            return \App\Models\Tenant::query()->exists()
                ? redirect('/login')
                : redirect('/setup');
        }

        return view('licenca.index', [
            'estado'      => $estado,
            'fingerprint' => MachineFingerprint::atual(),
            'pedido'      => $this->pedidoGuardado(),
            'temServidor' => trim((string) config('licensing.checkin_url')) !== '',
            'ligacao'     => $this->diagnosticarLigacao(),
        ]);
    }

    /**
     * Estado da ligação ao fornecedor, para o ecrã mostrar em vez de deixar o
     * cliente a adivinhar. Distingue os três casos que se confundiam num só
     * "sem ligação": sem URL configurado, sem internet, e servidor a recusar.
     */
    private function diagnosticarLigacao(): array
    {
        $url = $this->urlDoServidor('request');

        if (!$url) {
            return ['ok' => false, 'estado' => 'sem_configuracao', 'url' => null,
                'mensagem' => 'Servidor do fornecedor não configurado nesta instalação.'];
        }

        try {
            // HEAD ao servidor: só queremos saber se responde, não o conteúdo.
            $r = Http::timeout(6)->get(preg_replace('#/api/license/request$#', '/up', $url));

            return $r->successful()
                ? ['ok' => true, 'estado' => 'ligado', 'url' => $url, 'mensagem' => 'Ligado ao fornecedor.']
                : ['ok' => false, 'estado' => 'servidor_erro', 'url' => $url,
                   'mensagem' => 'O servidor do fornecedor respondeu com erro (HTTP ' . $r->status() . ').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'estado' => 'sem_rede', 'url' => $url,
                'mensagem' => self::explicarFalhaDeRede($e, $url)];
        }
    }

    /**
     * Traduz a excepção para algo accionável. Um erro de certificado manda o
     * cliente investigar a firewall se a mensagem for genérica — e o problema
     * está na instalação, não na rede dele.
     */
    private static function explicarFalhaDeRede(\Throwable $e, string $url): string
    {
        $m = $e->getMessage();
        $host = parse_url($url, PHP_URL_HOST);

        if (stripos($m, 'SSL cert') !== false || stripos($m, 'certificate') !== false
            || stripos($m, 'cURL error 60') !== false) {
            return 'Falha de certificado (SSL) — faltam os certificados raiz nesta instalação. '
                . 'É um problema da instalação, não da sua internet: contacte o fornecedor.';
        }

        if (stripos($m, 'Could not resolve host') !== false || stripos($m, 'cURL error 6') !== false) {
            return 'Não foi possível resolver o endereço ' . $host . ' (DNS/internet).';
        }

        if (stripos($m, 'timed out') !== false || stripos($m, 'cURL error 28') !== false) {
            return 'O servidor ' . $host . ' não respondeu a tempo.';
        }

        return 'Não foi possível contactar ' . $host . ' (verifique a internet ou a firewall).';
    }

    /**
     * O cliente SOLICITA uma licença: envia os dados da empresa e a impressão
     * digital desta máquina ao fornecedor. Guarda o código do pedido para
     * depois poder vir buscar a licença sozinho.
     */
    public function solicitar(Request $request)
    {
        $dados = $request->validate([
            'empresa'      => 'required|string|min:3|max:255',
            'nif'          => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:255',
            'telefone'     => 'nullable|string|max:30',
            'responsavel'  => 'nullable|string|max:255',
            'utilizadores' => 'nullable|integer|min:1|max:500',
            'observacoes'  => 'nullable|string|max:1000',
        ]);

        $dados['fingerprint'] = MachineFingerprint::atual();
        $dados['versao'] = (string) config('licensing.update.current');

        $url = $this->urlDoServidor('request');
        // Porque é que não deu — para o cliente e o suporte não ficarem a
        // adivinhar. Antes dizia sempre "sem ligação", mesmo quando o problema
        // era outro (URL em falta, ou o servidor a responder com erro).
        $motivo = 'Servidor do fornecedor não configurado nesta instalação.';

        if ($url) {
            try {
                $resp = Http::timeout(15)->acceptJson()->post($url, $dados);

                if ($resp->successful() && $resp->json('codigo')) {
                    $this->guardarPedido([
                        'codigo'  => $resp->json('codigo'),
                        'estado'  => $resp->json('estado') ?? 'pendente',
                        'empresa' => $dados['empresa'],
                        'enviado' => true,
                    ]);

                    return back()->with('ok', 'Pedido enviado. Código: ' . $resp->json('codigo')
                        . '. Assim que for aprovado, a licença é instalada automaticamente.');
                }

                // Respondeu, mas não como esperávamos: mostrar o que disse.
                $erro = $resp->json('message') ?? $resp->json('erro') ?? '';
                $motivo = 'O servidor respondeu HTTP ' . $resp->status()
                    . ($erro ? ' — ' . (is_string($erro) ? $erro : json_encode($erro)) : '') . '.';
            } catch (\Throwable $e) {
                $motivo = self::explicarFalhaDeRede($e, $url);
            }
        }

        // Não deu online: gera um código que o cliente envia ao fornecedor por
        // outra via (email, WhatsApp). É só um transporte de dados — quem emite
        // a licença continua a ser o fornecedor.
        $offline = base64_encode(json_encode($dados, JSON_UNESCAPED_UNICODE));
        $this->guardarPedido([
            'codigo'  => null,
            'estado'  => 'offline',
            'empresa' => $dados['empresa'],
            'enviado' => false,
            'pacote'  => $offline,
            'motivo'  => $motivo,
        ]);

        return back()->with('aviso', 'Não foi possível enviar o pedido: ' . $motivo
            . ' Foi gerado um código para enviar por email ou WhatsApp.');
    }

    /** Vai ver se o pedido já foi aprovado — e se sim, instala a licença. */
    public function verificarPedido()
    {
        $pedido = $this->pedidoGuardado();
        $url = $this->urlDoServidor('request');

        if (!$pedido || empty($pedido['codigo']) || !$url) {
            return back()->with('aviso', 'Não há pedido online para consultar.');
        }

        try {
            $resp = Http::timeout(15)->acceptJson()->get($url . '/' . $pedido['codigo']);
        } catch (\Throwable $e) {
            return back()->with('aviso', 'Sem ligação ao fornecedor. Tente mais tarde.');
        }

        if (!$resp->successful()) {
            return back()->with('aviso', 'Não foi possível consultar o pedido.');
        }

        if ($resp->json('estado') === 'recusado') {
            return back()->with('erro', 'Pedido recusado: ' . ($resp->json('motivo') ?? '—'));
        }

        if ($resp->json('estado') !== 'aprovado' || !$resp->json('licenca')) {
            return back()->with('aviso', 'O pedido ainda aguarda aprovação do fornecedor.');
        }

        // Aprovado: verifica a assinatura ANTES de instalar (o servidor pode
        // mentir; a assinatura não).
        $token = $resp->json('licenca');
        $estado = $this->licencas->verificarToken($token, ['fingerprint' => MachineFingerprint::atual()]);

        if ($estado->estado === LicenseState::INVALIDA) {
            return back()->with('erro', 'A licença recebida foi recusada: ' . $estado->motivo);
        }

        $this->licencas->store()->guardarToken($token);
        $this->esquecerPedido();

        // Instalada: seguir para o passo que falta em vez de voltar a este ecrã.
        return \App\Models\Tenant::query()->exists()
            ? redirect('/login')->with('ok', 'Licença instalada. Já pode entrar.')
            : redirect('/setup')->with('ok', 'Licença instalada. Vamos criar a sua empresa.');
    }

    private function urlDoServidor(string $caminho): ?string
    {
        $base = trim((string) config('licensing.checkin_url'));
        if ($base === '') {
            return null;
        }

        // .../api/license/checkin -> .../api/license/request
        return preg_replace('#/checkin$#', '/' . $caminho, $base);
    }

    private function caminhoDoPedido(): string
    {
        return dirname((string) config('licensing.license_path')) . DIRECTORY_SEPARATOR . 'pedido.json';
    }

    private function pedidoGuardado(): ?array
    {
        $f = $this->caminhoDoPedido();
        if (!is_file($f)) {
            return null;
        }
        $j = json_decode((string) @file_get_contents($f), true);

        return is_array($j) ? $j : null;
    }

    /** Esquece o pedido: cumpriu-se, e um pedido velho só confunde. */
    private function esquecerPedido(): void
    {
        @unlink($this->caminhoDoPedido());
    }

    private function guardarPedido(array $dados): void
    {
        $f = $this->caminhoDoPedido();
        @mkdir(dirname($f), 0775, true);
        @file_put_contents($f, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Instala um token colado no formulário, depois de o verificar. */
    public function guardar(Request $request)
    {
        $dados = $request->validate([
            'token' => 'required|string|min:20',
        ]);

        $token = trim($dados['token']);
        $estado = $this->licencas->verificarToken($token, [
            'fingerprint' => MachineFingerprint::atual(),
        ]);

        // Recusa uma licença que nem sequer é válida (assinatura/máquina/
        // formato). Estados brandos (aviso/expirada) deixam-se instalar — o
        // cliente pode estar a substituir por uma renovada.
        if ($estado->estado === \App\Services\Licensing\LicenseState::INVALIDA) {
            return back()->withInput()->with('erro', 'Licença recusada: ' . $estado->motivo);
        }

        $this->licencas->store()->guardarToken($token);
        $this->esquecerPedido();

        // Mesma regra do caminho online: seguir para o passo em falta.
        if ($estado->bloqueiaTudo()) {
            return redirect()->route('licenca.index')
                ->with('erro', 'Licença instalada mas não activa: ' . $estado->motivo);
        }

        return \App\Models\Tenant::query()->exists()
            ? redirect('/login')->with('ok', 'Licença instalada. Já pode entrar.')
            : redirect('/setup')->with('ok', 'Licença instalada. Vamos criar a sua empresa.');
    }
}
