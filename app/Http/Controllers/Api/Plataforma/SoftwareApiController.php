<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Helpers\SAFTHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\SoftwareSetting;
use App\Models\Tenant;
use App\Services\AGT\AGTClient;
use App\Services\AGT\AGTProducerStore;
use App\Services\AGT\GestaoAgt;
use App\Services\AGT\RecusaComDetalhes;
use App\Services\Plataforma\FicheiroDeAmbiente;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * AS DEFINIÇÕES DO SOFTWARE — o SOS ERP enquanto produtor certificado pela AGT.
 *
 * As credenciais do produtor, o número de certificação por ambiente, a chave
 * RSA que assina TODOS os documentos de TODAS as empresas, os bloqueios de
 * apagar documentos, e uma consola para testar a ligação à AGT em nome de uma
 * empresa. As regras vêm do componente, onde foram aprendidas uma a uma.
 *
 * O QUE MUDOU DE SUBSTÂNCIA:
 *
 *  · O ESTADO DAS EMPRESAS FAZIA DUAS CONSULTAS POR EMPRESA (definições e
 *    séries) e duas idas ao disco, a cada render. Agora são duas consultas para
 *    todas.
 *  · A ESCRITA DO `.env` saiu para `FicheiroDeAmbiente`: um ensaio deste ecrã
 *    escrevia no `.env` verdadeiro da máquina.
 *  · A CONSOLA DA AGT SÓ LÊ. Testar a ligação, listar, consultar e obter o
 *    estado — nada que submeta um documento. Não há botão para enviar.
 */
class SoftwareApiController extends Controller
{
    /** Os bloqueios de apagar, um por tipo de documento. */
    public const BLOQUEIOS = [
        'block_delete_sales_invoice' => 'Facturas (FT)',
        'block_delete_invoice_receipt' => 'Facturas-recibo (FR)',
        'block_delete_pos_invoice' => 'Facturas do ponto de venda',
        'block_delete_proforma' => 'Facturas pró-forma',
        'block_delete_receipt' => 'Recibos',
        'block_delete_credit_note' => 'Notas de crédito',
        // A nota de débito lia a chave da nota de crédito: são documentos
        // distintos e cada um tem o seu interruptor.
        'block_delete_debit_note' => 'Notas de débito',
    ];

    public function index(Request $request): JsonResponse
    {
        $ambiente = AGTProducerStore::normalizar($request->query('ambiente', 'sandbox'));

        return response()->json([
            'bloqueios' => collect(self::BLOQUEIOS)->map(fn ($r, $k) => [
                'chave' => $k,
                'rotulo' => __($r),
                'ligado' => (bool) SoftwareSetting::get('invoicing', $k, false),
            ])->values(),
            'produtor' => $this->produtor($ambiente),
            'empresas' => $this->estadoDasEmpresas(),
            'chaves_saft' => SAFTHelper::keysExist(),
            'certificado_global' => filled(softwareSetting('invoicing', 'saft_software_cert')),
        ]);
    }

    public function guardarBloqueios(Request $request): JsonResponse
    {
        $d = $request->validate(collect(self::BLOQUEIOS)->mapWithKeys(fn ($r, $k) => [$k => ['boolean']])->all());

        foreach (array_keys(self::BLOQUEIOS) as $chave) {
            SoftwareSetting::set('invoicing', $chave, (bool) ($d[$chave] ?? false));
        }

        return response()->json(['message' => __('Bloqueios de apagar documentos guardados.')]);
    }

    public function guardarProdutor(Request $request, FicheiroDeAmbiente $ficheiro): JsonResponse
    {
        $ambiente = AGTProducerStore::normalizar($request->input('ambiente'));
        $proprias = AGTProducerStore::credenciais($ambiente)['proprias'];

        $d = $request->validate([
            'ambiente' => ['required', 'in:sandbox,production'],
            'username' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            // «DEIXAR VAZIO PARA MANTER» só vale quando há uma palavra-passe
            // PRÓPRIA deste ambiente. A partilhada não conta: gravar só o
            // utilizador autenticava com o utilizador de um ambiente e a
            // palavra-passe do outro.
            'password' => [$proprias ? 'nullable' : 'required', 'string', 'max:1000', 'not_regex:/[\r\n]/'],
            'certificacao' => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n]/'],
        ], [
            'username.not_regex' => __('O utilizador não pode ter quebras de linha.'),
            'password.not_regex' => __('A palavra-passe não pode ter quebras de linha.'),
        ], [
            'username' => __('utilizador'),
            'password' => __('palavra-passe'),
        ]);

        $prefixo = $ambiente === 'production' ? 'AGT_PRODUCTION_API' : 'AGT_SANDBOX_API';
        $valores = ["{$prefixo}_USERNAME" => trim($d['username'])];

        if (filled($d['password'] ?? null)) {
            $valores["{$prefixo}_PASSWORD"] = $d['password'];
        }

        try {
            $ficheiro->escrever($valores);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['username' => $e->getMessage()]);
        }

        Artisan::call('config:clear');

        config(["services.agt.{$ambiente}.username" => $valores["{$prefixo}_USERNAME"]]);

        if (isset($valores["{$prefixo}_PASSWORD"])) {
            config(["services.agt.{$ambiente}.password" => $valores["{$prefixo}_PASSWORD"]]);
        }

        $certificacao = trim((string) ($d['certificacao'] ?? ''));

        if ($certificacao !== '') {
            SoftwareSetting::set('invoicing', "saft_software_cert_{$ambiente}", $certificacao, 'string',
                'Processo de Certificação AGT — '.$this->rotulo($ambiente));
        }

        return response()->json([
            'message' => __('Credenciais do produtor para :ambiente guardadas.', ['ambiente' => $this->rotulo($ambiente)]),
            'produtor' => $this->produtor($ambiente),
        ]);
    }

    public function limparProdutor(Request $request, FicheiroDeAmbiente $ficheiro): JsonResponse
    {
        $d = $request->validate(['ambiente' => ['required', 'in:sandbox,production']]);
        $ambiente = $d['ambiente'];
        $prefixo = $ambiente === 'production' ? 'AGT_PRODUCTION_API' : 'AGT_SANDBOX_API';

        $ficheiro->escrever(["{$prefixo}_USERNAME" => '', "{$prefixo}_PASSWORD" => '']);
        Artisan::call('config:clear');
        config(["services.agt.{$ambiente}.username" => null, "services.agt.{$ambiente}.password" => null]);

        return response()->json([
            'message' => __('Credenciais do produtor para :ambiente removidas.', ['ambiente' => $this->rotulo($ambiente)]),
            'produtor' => $this->produtor($ambiente),
        ]);
    }

    /** O que falta a uma empresa para falar com a AGT. */
    public function prontidao(int $empresa): JsonResponse
    {
        $t = Tenant::findOrFail($empresa);
        $d = InvoicingSettings::where('tenant_id', $t->id)->first();
        $ambiente = $d?->agt_environment ?? 'sandbox';

        return response()->json([
            'ambiente' => $ambiente,
            'itens' => [
                ['chave' => 'produtor', 'rotulo' => __('Credenciais do produtor'), 'ok' => AGTProducerStore::temCredenciais($ambiente)],
                ['chave' => 'rsa_produtor', 'rotulo' => __('Chave RSA do produtor'), 'ok' => AGTProducerStore::temChaves($ambiente)],
                ['chave' => 'certificado', 'rotulo' => __('Número de certificação'), 'ok' => AGTProducerStore::numeroCertificacao($ambiente) !== ''],
                ['chave' => 'nif', 'rotulo' => __('NIF da empresa'), 'ok' => filled($t->nif ?? $t->tax_id ?? null)],
                ['chave' => 'rsa_contribuinte', 'rotulo' => __('Chave RSA do contribuinte'), 'ok' => $this->temChaveDoContribuinte($t->id)],
                ['chave' => 'series', 'rotulo' => __('Séries activas'), 'ok' => InvoicingSeries::where('tenant_id', $t->id)->where('is_active', true)->exists()],
            ],
        ]);
    }

    /**
     * O SUPER ADMIN TROCA O AMBIENTE PELA MESMA PORTA QUE A EMPRESA.
     *
     * Escrevia `agt_environment` directamente: saltava a prontidão de produção
     * e a confirmação — e era, das duas portas, a de quem mexe em muitas
     * empresas de seguida. Passa pelo GestaoAgt::activarAmbiente(), com as
     * mesmas guardas, o mesmo `confirmar` e o mesmo rasto na trilha da empresa.
     */
    public function aplicarAmbiente(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa' => ['required', 'integer', 'exists:tenants,id'],
            'ambiente' => ['required', 'in:sandbox,production'],
            'confirmar' => ['nullable', 'boolean'],
        ]);

        $gestao = new GestaoAgt((int) $d['empresa']);
        $confirmar = filter_var($request->input('confirmar'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;

        try {
            $r = $gestao->activarAmbiente($d['ambiente'], $confirmar);
        } catch (DomainException $e) {
            return response()->json(
                ['message' => $e->getMessage()] + ($e instanceof RecusaComDetalhes ? $e->detalhes : []),
                422
            );
        }

        return response()->json([
            'message' => $r['tipo'] === 'info'
                ? $r['mensagem']
                : __('Ambiente AGT da empresa passou a :ambiente.', ['ambiente' => $this->rotulo($d['ambiente'])]),
            'tipo' => $r['tipo'],
            'ambiente_activo' => $gestao->ambienteActivo(),
            'pendentes_por_ambiente' => $r['pendentes_por_ambiente'],
        ]);
    }

    public function testarLigacao(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa' => ['required', 'integer', 'exists:tenants,id'],
            'ambiente' => ['required', 'in:sandbox,production'],
        ]);

        $inicio = microtime(true);

        try {
            $r = (new AGTClient((int) $d['empresa'], $d['ambiente']))->testConnection();
        } catch (\Throwable $e) {
            report($e);
            $r = ['success' => false, 'error' => $e->getMessage(), 'environment' => $d['ambiente']];
        }

        return response()->json($this->resultado($r, $inicio));
    }

    /** Listar, consultar ou obter o estado — só leitura. */
    public function operacao(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa' => ['required', 'integer', 'exists:tenants,id'],
            'ambiente' => ['required', 'in:sandbox,production'],
            'operacao' => ['required', 'in:listarFacturas,consultarFactura,obterEstado'],
            'pedido' => ['required_if:operacao,obterEstado', 'nullable', 'string', 'max:100'],
            'documento' => ['required_if:operacao,consultarFactura', 'nullable', 'string', 'max:100'],
            'de' => ['required_if:operacao,listarFacturas', 'nullable', 'date'],
            'ate' => ['required_if:operacao,listarFacturas', 'nullable', 'date', 'after_or_equal:de'],
        ]);

        $inicio = microtime(true);

        try {
            $cliente = new AGTClient((int) $d['empresa'], $d['ambiente']);

            $r = match ($d['operacao']) {
                'obterEstado' => $cliente->getStatus(trim((string) $d['pedido'])),
                'consultarFactura' => $cliente->getInvoice(trim((string) $d['documento'])),
                default => $cliente->listInvoices(['date_from' => $d['de'], 'date_to' => $d['ate']]),
            };
        } catch (\Throwable $e) {
            report($e);
            $r = ['success' => false, 'error' => $e->getMessage()];
        }

        return response()->json($this->resultado($r + ['operation' => $d['operacao'], 'environment' => $d['ambiente']], $inicio));
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    /**
     * O estado do produtor num ambiente.
     *
     * Nunca traz a palavra-passe nem a chave privada — só diz se estão
     * configuradas e se são as do ambiente ou as partilhadas, e a impressão
     * digital da chave pública para se confirmar qual está instalada.
     */
    private function produtor(string $ambiente): array
    {
        $c = AGTProducerStore::credenciais($ambiente);
        $chave = null;

        if (AGTProducerStore::temChaves($ambiente)) {
            try {
                $disco = Storage::disk('local');
                $caminho = AGTProducerStore::publicKeyPath($ambiente);
                $pem = $disco->get($caminho);
                $detalhes = ($recurso = openssl_pkey_get_public($pem)) ? openssl_pkey_get_details($recurso) : [];

                $chave = [
                    'bits' => $detalhes['bits'] ?? null,
                    'tipo' => ($detalhes['type'] ?? null) === OPENSSL_KEYTYPE_RSA ? 'RSA' : __('outro'),
                    'impressao' => strtoupper(substr(hash('sha256', $pem), 0, 32)),
                    'actualizada' => date('d/m/Y H:i', $disco->lastModified($caminho)),
                    'propria' => AGTProducerStore::temChavesProprias($ambiente),
                ];
            } catch (\Throwable $e) {
                $chave = ['erro' => $e->getMessage()];
            }
        }

        $certificacaoPropria = AGTProducerStore::temCertificacaoPropria($ambiente);

        return [
            'ambiente' => $ambiente,
            // Só o utilizador PRÓPRIO do ambiente vai no campo; o herdado vai
            // no marcador. Pô-lo no campo fazia parecer configurado.
            'username' => $c['proprias'] ? $c['username'] : '',
            'username_herdado' => $c['proprias'] ? '' : $c['username'],
            'credenciais_proprias' => $c['proprias'],
            'tem_credenciais' => AGTProducerStore::temCredenciais($ambiente),
            'certificacao' => $certificacaoPropria ? AGTProducerStore::numeroCertificacao($ambiente) : '',
            'certificacao_herdada' => $certificacaoPropria ? '' : AGTProducerStore::numeroCertificacao($ambiente),
            'chave' => $chave,
        ];
    }

    /** DUAS CONSULTAS PARA TODAS as empresas — eram duas por empresa. */
    private function estadoDasEmpresas(): array
    {
        $empresas = Tenant::orderBy('name')->get(['id', 'name', 'nif']);
        $definicoes = InvoicingSettings::whereIn('tenant_id', $empresas->pluck('id'))
            ->get(['tenant_id', 'agt_environment', 'agt_auto_submit'])->keyBy('tenant_id');
        $series = InvoicingSeries::whereIn('tenant_id', $empresas->pluck('id'))->where('is_active', true)
            ->selectRaw('tenant_id, COUNT(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');

        return $empresas->map(fn (Tenant $t) => [
            'id' => $t->id,
            'nome' => $t->name,
            'nif' => $t->nif,
            'ambiente' => $definicoes[$t->id]->agt_environment ?? 'sandbox',
            'submissao_automatica' => (bool) ($definicoes[$t->id]->agt_auto_submit ?? false),
            'series' => (int) ($series[$t->id] ?? 0),
            'chave_do_contribuinte' => $this->temChaveDoContribuinte($t->id),
        ])->values()->all();
    }

    private function temChaveDoContribuinte(int $empresa): bool
    {
        $disco = Storage::disk('local');

        return $disco->exists("agt/tenants/{$empresa}/private_key.pem") && $disco->exists("agt/tenants/{$empresa}/public_key.pem");
    }

    private function resultado(array $r, float $inicio): array
    {
        return $r + [
            'testado_em' => now()->format('d/m/Y H:i:s'),
            'duracao_ms' => (int) ((microtime(true) - $inicio) * 1000),
        ];
    }

    private function rotulo(string $ambiente): string
    {
        return $ambiente === 'production' ? __('produção') : __('homologação');
    }
}
