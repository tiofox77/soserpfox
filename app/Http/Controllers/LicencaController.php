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

    public function index()
    {
        return view('licenca.index', [
            'estado'      => $this->licencas->estado(true),
            'fingerprint' => MachineFingerprint::atual(),
            'pedido'      => $this->pedidoGuardado(),
            'temServidor' => trim((string) config('licensing.checkin_url')) !== '',
        ]);
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
            } catch (\Throwable $e) {
                // Sem rede: cai no modo offline em baixo.
            }
        }

        // Sem internet (ou sem servidor): gera um código que o cliente envia ao
        // fornecedor por outra via (email, WhatsApp, telefone). É só um
        // transporte de dados — quem emite a licença continua a ser o vendor.
        $offline = base64_encode(json_encode($dados, JSON_UNESCAPED_UNICODE));
        $this->guardarPedido([
            'codigo'   => null,
            'estado'   => 'offline',
            'empresa'  => $dados['empresa'],
            'enviado'  => false,
            'pacote'   => $offline,
        ]);

        return back()->with('aviso', 'Sem ligação ao fornecedor. Foi gerado um código de pedido '
            . 'para enviar por email ou WhatsApp.');
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

        return redirect()->route('licenca.index')->with('ok', 'Licença instalada com sucesso.');
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

        return redirect()->route('licenca.index')
            ->with('ok', 'Licença instalada. Estado: ' . $estado->estado . '.');
    }
}
