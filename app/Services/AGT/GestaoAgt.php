<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTCommunicationLog;
use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A CONFIGURAÇÃO AGT DE UMA EMPRESA — a única implementação.
 *
 * Tudo o que o ecrã de sempre (Livewire) e o ecrã em React fazem à AGT
 * passa por aqui: os dois ambientes em separado, as chaves de cada um, a
 * activação deliberada, a sincronização das séries, o reenvio das
 * submissões, as consultas e a ficha do contribuinte. Os ecrãs só traduzem
 * o resultado em avisos.
 *
 * Há um ambiente ACTIVO, o que assina e transmite os documentos reais, e há
 * o ambiente que se está a VER. Eram a mesma coisa, e isso obrigava a pôr a
 * empresa em produção só para lá instalar as chaves — ou, pior, bastava
 * espreitar a homologação e guardar por outro motivo qualquer para uma
 * empresa em produção cair para homologação sem ninguém dar por isso. Por
 * isso mudar de ambiente é `activarAmbiente()`, e mais nada o muda.
 */
class GestaoAgt
{
    public const AMBIENTES = ['sandbox' => 'Homologação', 'production' => 'Produção'];

    public const OPERACOES = ['listarFacturas' => 'Listar facturas', 'consultarFactura' => 'Consultar factura', 'obterEstado' => 'Obter estado do pedido'];

    public function __construct(private readonly int $tenantId)
    {
    }

    public static function normalizar(?string $ambiente): string
    {
        return $ambiente === 'production' ? 'production' : 'sandbox';
    }

    public static function rotulo(?string $ambiente): string
    {
        return self::AMBIENTES[self::normalizar($ambiente)];
    }

    /**
     * A empresa que este utilizador pode configurar. O super admin da
     * plataforma pode qualquer uma; os outros, só as suas.
     */
    public static function empresaPermitida(?User $user, ?int $tenantId): ?int
    {
        if (!$tenantId || !Tenant::whereKey($tenantId)->exists()) {
            return null;
        }

        if ($user?->isPlatformSuperAdmin()) {
            return $tenantId;
        }

        return $user?->tenants()->whereKey($tenantId)->exists() ? $tenantId : null;
    }

    /* ─── Ler ─────────────────────────────────────────────────────────── */

    /** O que está gravado, com os valores de quem ainda não gravou nada. */
    public function ler(): array
    {
        $s = InvoicingSettings::where('tenant_id', $this->tenantId)->first();

        return [
            'agt_environment' => self::normalizar($s?->agt_environment),
            'agt_auto_submit' => (bool) ($s?->agt_auto_submit ?? false),
            'agt_eac_code' => (string) ($s?->agt_eac_code ?? ''),
            'agt_require_validation' => (bool) ($s?->agt_require_validation ?? true),
        ];
    }

    /** O ambiente que está mesmo a emitir. */
    public function ambienteActivo(): string
    {
        return $this->ler()['agt_environment'];
    }

    /**
     * Chaves DO AMBIENTE pedido: o Portal do Contribuinte entrega pares
     * diferentes para homologação e para produção. E as credenciais do
     * produtor DESSE ambiente: as de homologação não autenticam contra a
     * AGT real.
     */
    public function chaves(string $ambiente): array
    {
        $ambiente = self::normalizar($ambiente);

        return [
            'publica' => Storage::disk('local')->exists(AGTKeyStore::publicKeyPath($this->tenantId, $ambiente)),
            'privada' => Storage::disk('local')->exists(AGTKeyStore::privateKeyPath($this->tenantId, $ambiente)),
            'produtor' => AGTProducerStore::temCredenciais($ambiente),
        ];
    }

    /**
     * Estado dos DOIS ambientes de uma vez. Ver um de cada vez escondia o
     * essencial: que produção ainda não tem chaves e portanto não pode ser
     * activada.
     */
    public function estadoAmbientes(string $aVer): array
    {
        $aVer = self::normalizar($aVer);
        $activo = $this->ambienteActivo();
        $estado = [];

        foreach (self::AMBIENTES as $ambiente => $rotulo) {
            $estado[$ambiente] = [
                'rotulo' => $rotulo,
                'chaves' => AGTKeyStore::hasKeyPair($this->tenantId, $ambiente),
                'activo' => $activo === $ambiente,
                'a_ver' => $aVer === $ambiente,
                'produtor' => AGTProducerStore::temChaves($ambiente) && AGTProducerStore::temCredenciais($ambiente),
            ];
        }

        return $estado;
    }

    public function relatorio(string $ambiente): array
    {
        return (new AGTService($this->tenantId))->getComplianceReport(self::normalizar($ambiente));
    }

    /**
     * Só o ambiente pedido: homologação e produção são universos separados
     * e misturá-los faz um documento de teste passar por real.
     */
    public function logs(string $ambiente, int $limite = 20): Collection
    {
        return AGTCommunicationLog::where('tenant_id', $this->tenantId)
            ->where('agt_environment', self::normalizar($ambiente))
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Histórico completo: documentos processados não podem "desaparecer"
     * quando passam de submitted para validated/rejected.
     */
    public function submissoes(string $ambiente, int $limite = 50): Collection
    {
        return AGTSubmission::where('tenant_id', $this->tenantId)
            ->where('agt_environment', self::normalizar($ambiente))
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Séries registadas noutro ambiente não são mostradas como registadas:
     * uma série de homologação não existe em produção.
     */
    public function series(string $ambiente): Collection
    {
        $ambiente = self::normalizar($ambiente);

        return InvoicingSeries::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('agt_series_id')->orWhere('agt_environment', $ambiente))
            ->get();
    }

    /** Classes CAE (5 dígitos) — é o nível que a AGT espera em eacCode. */
    public static function classesCae(): Collection
    {
        return Cache::remember('agt_cae_classes', 3600, fn () => DB::table('agt_cae_codes')
            ->where('is_active', true)
            ->where('level', 'class')
            ->orderBy('code')
            ->get(['code', 'description']));
    }

    /** O que falta para falar com a AGT neste ambiente. */
    public function emFalta(string $ambiente): array
    {
        $c = $this->chaves($ambiente);
        $falta = [];

        if (!$c['produtor']) {
            $falta[] = 'credenciais globais do produtor';
        }
        if (!$c['publica']) {
            $falta[] = 'chave pública do Portal AGT';
        }
        if (!$c['privada']) {
            $falta[] = 'chave privada do Portal AGT';
        }

        return $falta;
    }

    /* ─── Definições ──────────────────────────────────────────────────── */

    public static function regras(): array
    {
        return [
            'agt_auto_submit' => ['boolean'],
            'agt_eac_code' => ['nullable', 'string', 'max:8'],
            'agt_require_validation' => ['boolean'],
        ];
    }

    /**
     * Sem agt_environment de propósito: mudar de ambiente é a acção
     * activarAmbiente(), não um efeito lateral de guardar o CAE.
     */
    public function guardar(array $d): void
    {
        $this->definicoes()->update([
            'agt_auto_submit' => (bool) ($d['agt_auto_submit'] ?? false),
            // CAE: vazio grava NULL, para o mapper cair no marcador em vez de ''
            'agt_eac_code' => ($d['agt_eac_code'] ?? null) ?: null,
            'agt_require_validation' => (bool) ($d['agt_require_validation'] ?? true),
        ]);
    }

    /**
     * Passa a emitir pelo ambiente pedido. Acção deliberada e separada do
     * Guardar: é ela que decide se os documentos desta empresa vão para a
     * AGT real ou para a de testes.
     *
     * @return array{tipo: string, mensagem: string}
     */
    public function activarAmbiente(string $novo): array
    {
        $novo = self::normalizar($novo);

        if ($novo === $this->ambienteActivo()) {
            return ['tipo' => 'info', 'mensagem' => __('Já é este o ambiente activo.')];
        }

        // Activar produção sem o par RSA de produção não dá para assinar
        // documento nenhum: a empresa ficava a falhar toda a facturação.
        if ($novo === 'production' && !AGTKeyStore::hasKeyPair($this->tenantId, 'production')) {
            throw new DomainException(__('Instale primeiro o par RSA de Produção do Portal do Contribuinte. Sem ele nenhum documento é assinado.'));
        }

        $this->definicoes()->update(['agt_environment' => $novo]);

        $rotulo = $novo === 'production' ? 'PRODUÇÃO' : 'Homologação';

        return ['tipo' => 'success', 'mensagem' => "Ambiente activo: {$rotulo}. Os próximos documentos seguem por aqui."];
    }

    /* ─── Chaves do contribuinte ──────────────────────────────────────── */

    /**
     * Valida o par e guarda-o no ambiente pedido, sem sobrepor o outro.
     * As chaves erradas saem como erros de validação com o campo certo.
     */
    public function guardarChaves(string $ambiente, string $publica, string $privada): string
    {
        $publica = trim($publica) . PHP_EOL;
        $privada = trim($privada) . PHP_EOL;
        $recursoPublico = @openssl_pkey_get_public($publica);
        $recursoPrivado = @openssl_pkey_get_private($privada);

        if (!$recursoPublico) {
            throw ValidationException::withMessages(['contributorPublicKey' => 'A chave pública não é um PEM RSA válido.']);
        }
        if (!$recursoPrivado) {
            throw ValidationException::withMessages(['contributorPrivateKey' => 'A chave privada não é um PEM RSA válido ou está protegida por password.']);
        }

        $detalhesPublico = openssl_pkey_get_details($recursoPublico);
        $detalhesPrivado = openssl_pkey_get_details($recursoPrivado);
        if (
            empty($detalhesPublico['rsa']['n'])
            || empty($detalhesPrivado['rsa']['n'])
            || !hash_equals($detalhesPublico['rsa']['n'], $detalhesPrivado['rsa']['n'])
        ) {
            throw ValidationException::withMessages(['contributorPrivateKey' => 'As chaves pública e privada não pertencem ao mesmo par RSA.']);
        }

        $ambiente = self::normalizar($ambiente);
        AGTKeyStore::store($this->tenantId, $publica, $privada, $ambiente);

        return 'Par de chaves guardado para ' . self::rotulo($ambiente) . '. O outro ambiente não foi alterado.';
    }

    /** Só as do ambiente pedido — o outro par tem de sobreviver. */
    public function removerChaves(string $ambiente): string
    {
        $ambiente = self::normalizar($ambiente);
        $dir = AGTKeyStore::directory($this->tenantId, $ambiente);
        Storage::disk('local')->delete(["{$dir}/public_key.pem", "{$dir}/private_key.pem"]);

        // Legado (sem ambiente): só se apaga quando se está no ambiente a que
        // essas chaves pertenciam, que a migração assumiu ser homologação.
        if ($ambiente === 'sandbox') {
            $legado = AGTKeyStore::legacyDirectory($this->tenantId);
            Storage::disk('local')->delete(["{$legado}/public_key.pem", "{$legado}/private_key.pem"]);
        }

        return 'Chaves de ' . self::rotulo($ambiente) . ' removidas. O outro ambiente não foi alterado.';
    }

    /* ─── Falar com a AGT ─────────────────────────────────────────────── */

    /**
     * Testa o ambiente pedido — é o que permite validar as chaves de
     * produção antes de as pôr a emitir. Sem os pré-requisitos não sai
     * pedido nenhum.
     */
    public function testarLigacao(string $ambiente): array
    {
        $this->exigirPreRequisitos($ambiente);

        try {
            return (new AGTClient($this->tenantId, self::normalizar($ambiente)))->testConnection();
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Acções que escrevem na AGT (registar séries, reenviar documentos)
     * correm sempre no ambiente ACTIVO. Executá-las enquanto se olha para o
     * outro registava séries em produção com o ecrã a dizer homologação.
     */
    public function conferir(string $aVer, string $accao): void
    {
        $aVer = self::normalizar($aVer);
        $activo = $this->ambienteActivo();

        if ($aVer === $activo) {
            return;
        }

        throw new AmbienteErradoException(
            'Está a ver ' . self::rotulo($aVer) . ' mas a empresa emite em ' . self::rotulo($activo)
            . ". {$accao} iria para " . self::rotulo($activo) . '. Volte ao ambiente activo ou active este.'
        );
    }

    public function sincronizarSeries(string $aVer): array
    {
        $this->conferir($aVer, 'A sincronização de séries');
        $this->exigirPreRequisitos($aVer);

        return (new AGTService($this->tenantId))->syncAllSeries();
    }

    /** Pergunta à AGT pelo estado das submissões que ficaram a meio. */
    public function actualizarEstados(): int
    {
        $service = new QueryService(InvoicingSettings::forTenant($this->tenantId));
        $actualizadas = 0;

        $submissoes = AGTSubmission::where('tenant_id', $this->tenantId)
            ->whereIn('status', ['pending', 'submitted'])
            ->whereNotNull('agt_reference')
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($submissoes as $submissao) {
            try {
                $resultado = $service->consultByRequestId($submissao->agt_reference);
                $codigo = (string) ($resultado['resultCode'] ?? '');
                $corpo = $resultado['response'] ?? [];

                if ($codigo === '0') {
                    $submissao->markAsValidated($submissao->agt_reference, $submissao->atcud, $corpo);
                    $actualizadas++;
                } elseif ($codigo === '2') {
                    $erros = collect($corpo['documentStatusList'] ?? [])
                        ->flatMap(fn ($linha) => $linha['errorList'] ?? [])
                        ->filter(fn ($erro) => is_array($erro) && !empty($erro['descriptionError']));
                    $submissao->markAsRejected(
                        'RESULT_CODE_2',
                        $erros->pluck('descriptionError')->implode('; ') ?: 'Documento rejeitado pela AGT.',
                        $corpo
                    );
                    $actualizadas++;
                }
            } catch (\Throwable $e) {
                Log::warning('GestaoAgt: falha ao actualizar submissao', [
                    'tenant_id' => $this->tenantId,
                    'submission_id' => $submissao->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $actualizadas;
    }

    /** Uma submissão DESTA empresa, ou nada — nem por id. */
    public function submissao(int $id): AGTSubmission
    {
        $s = AGTSubmission::find($id);

        if (!$s || (int) $s->tenant_id !== $this->tenantId) {
            throw new DomainException(__('Submissão não encontrada'));
        }

        return $s;
    }

    /**
     * Repor as tentativas de uma submissão esgotada e reenviá-la já.
     *
     * Quando a recusa foi culpa NOSSA (versão do schema fora de prazo,
     * imposto mal arredondado), o documento fica com as tentativas gastas e
     * ninguém conseguia reenviar sem ir à base. Corrigido o código, quem
     * gere a empresa carrega aqui: o contador volta a zero e o documento
     * segue no acto. O acto fica com rasto próprio (auditoria por modelo).
     */
    public function reporEReenviar(int $id, string $aVer): array
    {
        $this->conferir($aVer, 'O reenvio do documento');
        $s = $this->submissao($id);

        if ($s->status === AGTSubmission::STATUS_VALIDATED) {
            throw new DomainException(__('Este documento já foi validado pela AGT.'));
        }

        $s->update([
            'status' => AGTSubmission::STATUS_PENDING,
            'retry_count' => 0,
            'error_code' => null,
            'error_message' => null,
        ]);

        return $this->reenviar($id, $aVer);
    }

    public function reenviar(int $id, string $aVer): array
    {
        $this->conferir($aVer, 'O reenvio do documento');
        $s = $this->submissao($id);

        if (!$s->canRetry()) {
            throw new DomainException(__('Máximo de tentativas atingido'));
        }

        $documento = $s->document;
        if (!$documento) {
            throw new DomainException(__('Documento não encontrado'));
        }

        return (new AGTService($this->tenantId))->submitToAGT($documento);
    }

    public static function regrasDaConsulta(): array
    {
        return [
            'apiOperation' => 'required|in:' . implode(',', array_keys(self::OPERACOES)),
            'apiRequestId' => 'required_if:apiOperation,obterEstado|nullable|string|max:100',
            'apiDocumentNo' => 'required_if:apiOperation,consultarFactura|nullable|string|max:100',
            'apiDateFrom' => 'required_if:apiOperation,listarFacturas|nullable|date',
            'apiDateTo' => 'required_if:apiOperation,listarFacturas|nullable|date|after_or_equal:apiDateFrom',
        ];
    }

    /** Consulta: corre no ambiente que se está a ver. É só leitura. */
    public function consultar(string $operacao, array $p, string $ambiente): array
    {
        $inicio = microtime(true);

        try {
            $client = new AGTClient($this->tenantId, self::normalizar($ambiente));
            $resultado = match ($operacao) {
                'obterEstado' => $client->getStatus(trim((string) ($p['apiRequestId'] ?? ''))),
                'consultarFactura' => $client->getInvoice(trim((string) ($p['apiDocumentNo'] ?? ''))),
                default => $client->listInvoices([
                    'date_from' => $p['apiDateFrom'] ?? null,
                    'date_to' => $p['apiDateTo'] ?? null,
                ]),
            };
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['success' => false, 'error' => $e->getMessage()];
        }

        return array_merge($resultado, [
            'operation' => $operacao,
            'elapsed_ms' => (int) ((microtime(true) - $inicio) * 1000),
            'tested_at' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    /* ─── A ficha do contribuinte ─────────────────────────────────────── */

    public static function regrasDoContribuinte(): array
    {
        return [
            'tax_registration_number' => ['nullable', 'string', 'max:15'],
            'agt_establishment_number' => ['required', 'string', 'max:200'],
            'agt_notification_emails' => ['nullable', 'string', 'max:1000'],
            'agt_eac_code' => ['nullable', 'string', 'max:8'],
            'agt_auto_submit' => ['boolean'],
            'agt_require_validation' => ['boolean'],
        ];
    }

    public function lerContribuinte(): array
    {
        $s = InvoicingSettings::where('tenant_id', $this->tenantId)->first();
        $tenant = Tenant::find($this->tenantId);
        $ambiente = self::normalizar($s?->agt_environment);

        return [
            'agt_environment' => $ambiente,
            'tax_registration_number' => (string) ($tenant?->nif ?? $tenant?->tax_id ?? ''),
            'agt_establishment_number' => (string) ($s?->agt_establishment_number ?? 'SEDE'),
            'agt_auto_submit' => (bool) ($s?->agt_auto_submit ?? false),
            'agt_require_validation' => (bool) ($s?->agt_require_validation ?? true),
            'agt_notification_emails' => (string) ($s?->agt_notification_emails ?? ''),
            'agt_eac_code' => $s?->agt_eac_code,
            'produtor' => AGTProducerStore::temCredenciais($ambiente),
            'chave_legado' => Storage::disk('local')->exists($this->caminhoDaChaveLegado()),
        ];
    }

    /** Emails de aviso: um CSV que tem de ser todo válido. Devolve o primeiro inválido, ou nada. */
    public static function emailInvalido(?string $csv): ?string
    {
        foreach (array_filter(array_map('trim', explode(',', (string) $csv))) as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    public function guardarContribuinte(array $d): void
    {
        if ($invalido = self::emailInvalido($d['agt_notification_emails'] ?? null)) {
            throw ValidationException::withMessages(['agt_notification_emails' => "Email inválido: {$invalido}"]);
        }

        $this->definicoes()->update([
            'agt_establishment_number' => $d['agt_establishment_number'],
            'agt_auto_submit' => (bool) ($d['agt_auto_submit'] ?? false),
            'agt_require_validation' => (bool) ($d['agt_require_validation'] ?? true),
            'agt_notification_emails' => ($d['agt_notification_emails'] ?? null) ?: null,
            'agt_eac_code' => ($d['agt_eac_code'] ?? null) ?: null,
        ]);

        // O NIF é da empresa, não das definições da facturação.
        if (!empty($d['tax_registration_number'])) {
            Tenant::find($this->tenantId)?->update(['nif' => $d['tax_registration_number']]);
        }
    }

    /**
     * A CHAVE PRIVADA DO MODO ANTIGO, sem ambiente.
     *
     * O ecrã de configuração AGT guarda pares POR AMBIENTE — é o caminho de
     * hoje. Esta fica pelo ecrã do contribuinte, para as instalações que ainda
     * assinam com ela e que nunca correram o `agt:migrate-keys`.
     *
     * VALIDA-SE ANTES DE GRAVAR. Um texto que não seja um PEM RSA legível
     * escreve-se na mesma sem queixa nenhuma, e o defeito só aparece na
     * primeira factura que a AGT recusa — longe daqui, e sem ninguém ligar
     * uma coisa à outra. O erro sai no campo, como sai no par por ambiente.
     */
    public function guardarChaveLegado(string $pem): void
    {
        $pem = trim($pem) . PHP_EOL;

        if (!@openssl_pkey_get_private($pem)) {
            throw ValidationException::withMessages([
                'contributor_private_key' => 'A chave privada não é um PEM RSA válido ou está protegida por password.',
            ]);
        }

        Storage::disk('local')->put($this->caminhoDaChaveLegado(), $pem);
    }

    public function removerChaveLegado(): void
    {
        Storage::disk('local')->delete($this->caminhoDaChaveLegado());
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::firstOrCreate(
            ['tenant_id' => $this->tenantId],
            ['default_currency' => 'AOA']
        );
    }

    private function exigirPreRequisitos(string $ambiente): void
    {
        if ($falta = $this->emFalta($ambiente)) {
            throw new DomainException('Não foi enviado nenhum pedido à AGT. Falta configurar: ' . implode(', ', $falta) . '.');
        }
    }

    /**
     * A chave do modo antigo mora onde o resto do sistema arruma as chaves: na
     * pasta legada do `AGTKeyStore`, que é a mesma que o assinador lê quando
     * não encontra o par do ambiente. Escrito à mão, o caminho seguia o seu
     * caminho no dia em que aquele mudasse.
     */
    private function caminhoDaChaveLegado(): string
    {
        return AGTKeyStore::legacyDirectory($this->tenantId) . '/private_key.pem';
    }
}
