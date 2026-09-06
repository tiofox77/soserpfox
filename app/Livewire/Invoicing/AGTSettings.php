<?php

namespace App\Livewire\Invoicing;

use App\Models\Tenant;
use App\Services\AGT\AGTProducerStore;
use App\Services\AGT\AmbienteErradoException;
use App\Services\AGT\GestaoAgt;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CONFIGURAÇÕES AGT — o ecrã de sempre.
 *
 * Tudo o que fala com a AGT vive na `GestaoAgt`, partilhada com o ecrã em
 * React. Aqui fica só o estado do ecrã e a tradução do resultado em avisos.
 */
#[Layout('layouts.app')]
#[Title('Configurações AGT')]
class AGTSettings extends Component
{
    /**
     * Ambiente ACTIVO: o que assina e transmite os documentos reais desta
     * empresa. Só muda por acção deliberada (activarAmbiente), nunca por se
     * estar a olhar para o outro.
     */
    public string $agt_environment = 'sandbox';

    /**
     * Ambiente que se está a VER e a configurar no ecrã. Não é gravado.
     *
     * Eram a mesma propriedade, e isso obrigava a pôr a empresa em produção
     * só para lá instalar as chaves — ou, pior, bastava espreitar a
     * homologação e carregar em Guardar por outro motivo qualquer para uma
     * empresa em produção cair para homologação sem ninguém dar por isso.
     */
    public string $ambienteVista = 'sandbox';

    public bool $agt_auto_submit = false;

    /**
     * Código de actividade económica (CAE) da empresa. Vai em `eacCode` em
     * cada documento do payload — a AGT espera uma classe real da tabela.
     */
    public $agt_eac_code = '';
    public bool $agt_require_validation = true;

    // Estado
    public bool $isConnected = false;
    public bool $hasKeys = false;
    public bool $hasPublicKey = false;
    public bool $hasPrivateKey = false;
    public bool $hasGlobalCredentials = false;
    public string $contributorPublicKey = '';
    public string $contributorPrivateKey = '';
    public array $complianceReport = [];
    public array $connectionTest = [];
    public array $syncResult = [];
    public string $apiOperation = 'listarFacturas';
    public string $apiRequestId = '';
    public string $apiDocumentNo = '';
    public string $apiDateFrom = '';
    public string $apiDateTo = '';
    public array $apiOperationResult = [];
    public string $activeTab = 'config';
    public bool $isAdmin = false;
    #[Locked]
    public ?int $currentTenantId = null;

    // Logs
    public $recentLogs = [];
    public $pendingSubmissions = [];

    public function mount()
    {
        $user = auth()->user();
        $this->isAdmin = (bool) $user?->isPlatformSuperAdmin();

        $requestedTenantId = request()->integer('tenant') ?: null;
        $this->currentTenantId = $this->resolveAllowedTenantId($requestedTenantId)
            ?? $this->resolveAllowedTenantId(activeTenantId());

        $this->loadSettings();
        $this->checkStatus();
        $this->apiDateFrom = now()->subDays(30)->format('Y-m-d');
        $this->apiDateTo = now()->format('Y-m-d');
    }

    private function resolveAllowedTenantId(?int $tenantId): ?int
    {
        return GestaoAgt::empresaPermitida(auth()->user(), $tenantId);
    }

    private function ensureTenantAccess(): void
    {
        abort_unless(
            $this->currentTenantId
                && $this->resolveAllowedTenantId($this->currentTenantId) === $this->currentTenantId,
            403,
            'Empresa não seleccionada ou sem acesso.'
        );
    }

    private function exigirEdicao(): void
    {
        abort_unless(
            auth()->user()?->isPlatformSuperAdmin()
                || auth()->user()?->can('invoicing.agt.edit'),
            403
        );
    }

    private function gestao(): GestaoAgt
    {
        return new GestaoAgt((int) $this->currentTenantId);
    }

    public function selectTenant($tenantId): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $resolved = $this->resolveAllowedTenantId((int) $tenantId);
        abort_unless($resolved, 404);

        $this->redirectRoute(
            'invoicing.agt-settings',
            ['tenant' => $resolved],
            navigate: true
        );
    }

    private function loadSettings()
    {
        $this->agt_environment = 'sandbox';
        $this->agt_auto_submit = false;
        $this->agt_require_validation = true;

        if ($this->currentTenantId) {
            $d = $this->gestao()->ler();
            $this->agt_environment = $d['agt_environment'];
            $this->agt_auto_submit = $d['agt_auto_submit'];
            $this->agt_eac_code = $d['agt_eac_code'];
            $this->agt_require_validation = $d['agt_require_validation'];
        }

        // Abre no ambiente activo — é o que interessa a quem entra.
        $this->ambienteVista = $this->agt_environment;
    }

    private function checkStatus()
    {
        $ambiente = $this->ambienteActual();

        if (!$this->currentTenantId) {
            // Para admin sem tenant: sem chaves, relatório vazio.
            $this->hasPublicKey = $this->hasPrivateKey = $this->hasKeys = false;
            $this->hasGlobalCredentials = AGTProducerStore::temCredenciais($ambiente);
            $this->complianceReport = [
                'tenant_id' => null,
                'generated_at' => now()->toDateTimeString(),
                'keys_configured' => false,
                'api_configured' => false,
                'environment' => 'sandbox',
                'series' => ['total' => 0, 'registered' => 0, 'pending' => 0],
                'submissions' => ['total' => 0, 'pending' => 0, 'submitted' => 0, 'validated' => 0, 'rejected' => 0],
                'invoices_30_days' => ['total' => 0, 'with_hash' => 0, 'with_jws' => 0, 'with_atcud' => 0, 'agt_validated' => 0],
            ];
            $this->recentLogs = [];
            $this->pendingSubmissions = [];

            return;
        }

        $g = $this->gestao();
        $chaves = $g->chaves($ambiente);
        $this->hasPublicKey = $chaves['publica'];
        $this->hasPrivateKey = $chaves['privada'];
        $this->hasKeys = $this->hasPublicKey && $this->hasPrivateKey;
        $this->hasGlobalCredentials = $chaves['produtor'];
        // Ambiente do seletor, não o gravado — os cartões têm de mudar
        // assim que se troca, antes sequer de guardar.
        $this->complianceReport = $g->relatorio($ambiente);
        $this->loadLogs();
        $this->loadPendingSubmissions();
    }

    private function loadLogs()
    {
        $this->recentLogs = $this->currentTenantId
            ? $this->gestao()->logs($this->ambienteActual())->toArray()
            : [];
    }

    private function loadPendingSubmissions()
    {
        $this->pendingSubmissions = $this->currentTenantId
            ? $this->gestao()->submissoes($this->ambienteActual())->toArray()
            : [];
    }

    /**
     * Trocar o separador de ambiente recarrega tudo o que é específico dele:
     * os cartões do topo, o estado das chaves, as submissões e os logs.
     */
    public function updatedAmbienteVista(): void
    {
        $this->checkStatus();
    }

    /** Ambiente que se está a ver no ecrã. Não é o activo. */
    private function ambienteActual(): string
    {
        return GestaoAgt::normalizar($this->ambienteVista);
    }

    /** O ambiente que está mesmo a emitir, venha o seletor de onde vier. */
    public function ambienteActivo(): string
    {
        return GestaoAgt::normalizar($this->agt_environment);
    }

    public function aVerOAmbienteActivo(): bool
    {
        return $this->ambienteActual() === $this->ambienteActivo();
    }

    /** Estado dos DOIS ambientes de uma vez. */
    public function estadoAmbientes(): array
    {
        if ($this->currentTenantId) {
            return $this->gestao()->estadoAmbientes($this->ambienteActual());
        }

        $estado = [];
        foreach (GestaoAgt::AMBIENTES as $ambiente => $rotulo) {
            $estado[$ambiente] = [
                'rotulo' => $rotulo,
                'chaves' => false,
                'activo' => $this->ambienteActivo() === $ambiente,
                'a_ver' => $this->ambienteActual() === $ambiente,
                'produtor' => AGTProducerStore::temChaves($ambiente) && AGTProducerStore::temCredenciais($ambiente),
            ];
        }

        return $estado;
    }

    /** Passa a emitir pelo ambiente que se está a ver. */
    public function activarAmbiente(): void
    {
        $this->ensureTenantAccess();
        $this->exigirEdicao();

        try {
            $r = $this->gestao()->activarAmbiente($this->ambienteActual());
        } catch (DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->agt_environment = $this->gestao()->ambienteActivo();
        $this->checkStatus();
        $this->dispatch('notify', type: $r['tipo'], message: $r['mensagem']);
    }

    public function refreshSubmissionStatuses(): void
    {
        $this->ensureTenantAccess();

        $actualizadas = $this->gestao()->actualizarEstados();

        $this->checkStatus();
        $this->dispatch(
            'notify',
            type: 'success',
            message: $actualizadas
                ? "{$actualizadas} estado(s) actualizado(s) com a AGT."
                : 'Estados consultados. Nenhuma alteracao encontrada.'
        );
    }

    public function save()
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: __('Selecione um tenant para guardar configurações.'));

            return;
        }
        $this->ensureTenantAccess();

        // Sem agt_environment de propósito: mudar de ambiente é a acção
        // activarAmbiente(), não um efeito lateral de guardar o CAE.
        $this->gestao()->guardar([
            'agt_auto_submit' => $this->agt_auto_submit,
            'agt_eac_code' => $this->agt_eac_code,
            'agt_require_validation' => $this->agt_require_validation,
        ]);

        $this->dispatch('notify', type: 'success', message: __('Configurações AGT guardadas com sucesso!'));
    }

    public function saveContributorKeys(): void
    {
        $this->ensureTenantAccess();
        $this->exigirEdicao();

        $this->validate([
            'contributorPublicKey' => 'required|string|max:20000',
            'contributorPrivateKey' => 'required|string|max:20000',
        ]);

        // Uma chave errada sai do serviço como erro de validação, no campo
        // certo — o Livewire põe-no no sítio.
        $mensagem = $this->gestao()->guardarChaves($this->ambienteActual(), $this->contributorPublicKey, $this->contributorPrivateKey);

        $this->contributorPublicKey = '';
        $this->contributorPrivateKey = '';
        $this->checkStatus();
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    public function removeContributorKeys(): void
    {
        $this->ensureTenantAccess();
        $this->exigirEdicao();

        $mensagem = $this->gestao()->removerChaves($this->ambienteActual());

        $this->contributorPublicKey = '';
        $this->contributorPrivateKey = '';
        $this->checkStatus();
        $this->dispatch('notify', type: 'success', message: $mensagem);
    }

    public function testConnection()
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: __('Selecione um tenant para testar a conexão.'));

            return;
        }
        $this->ensureTenantAccess();

        try {
            $this->connectionTest = $this->gestao()->testarLigacao($this->ambienteActual());
        } catch (DomainException $e) {
            $this->syncResult = ['total' => 0, 'success' => 0, 'failed' => 1, 'error' => $e->getMessage(), 'details' => []];
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        $this->isConnected = $this->connectionTest['success'] ?? false;

        if ($this->isConnected) {
            $this->dispatch('notify', type: 'success', message: __('Conexão estabelecida com sucesso!'));
        } else {
            $this->dispatch('notify', type: 'error', message: $this->connectionTest['error'] ?? 'Falha na conexão');
        }
    }

    public function syncSeries()
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: __('Selecione um tenant para sincronizar séries.'));

            return;
        }
        $this->ensureTenantAccess();

        try {
            $result = $this->gestao()->sincronizarSeries($this->ambienteActual());
        } catch (AmbienteErradoException $e) {
            // Só o aviso: não se registou nada, e o resultado anterior fica.
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        } catch (\Exception $e) {
            $this->syncResult = ['total' => 0, 'success' => 0, 'failed' => 1, 'error' => $e->getMessage(), 'details' => []];
            $this->dispatch('notify', type: 'error', message: $e instanceof DomainException ? $e->getMessage() : __('Erro: :detalhe', ['detalhe' => $e->getMessage()]));

            return;
        }

        $this->syncResult = $result;

        if (($result['total'] ?? 0) === 0) {
            $this->dispatch('notify', type: 'info', message: __('Não existem séries activas pendentes de sincronização.'));
        } elseif ($result['success'] > 0 && $result['failed'] === 0) {
            $this->dispatch('notify', type: 'success', message: "{$result['success']} série(s) sincronizada(s) com sucesso!");
        } elseif ($result['failed'] > 0) {
            $this->dispatch('notify', type: 'warning', message: "{$result['success']} sincronizada(s); {$result['failed']} falharam.");
        }

        $this->checkStatus();
    }

    /** Repor as tentativas de uma submissão esgotada e reenviá-la já. */
    public function reporEReenviar(int $submissionId): void
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: __('Selecione um tenant para reenviar.'));

            return;
        }
        $this->ensureTenantAccess();

        $this->reenviarComAvisos(fn () => $this->gestao()->reporEReenviar($submissionId, $this->ambienteActual()));
    }

    public function retrySubmission(int $submissionId)
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: __('Selecione um tenant para reenviar.'));

            return;
        }
        $this->ensureTenantAccess();

        $this->reenviarComAvisos(fn () => $this->gestao()->reenviar($submissionId, $this->ambienteActual()));
    }

    private function reenviarComAvisos(callable $reenvio): void
    {
        try {
            $result = $reenvio();
        } catch (DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: __('Erro: :detalhe', ['detalhe' => $e->getMessage()]));

            return;
        }

        if ($result['success'] ?? false) {
            $this->dispatch('notify', type: 'success', message: __('Documento reenviado com sucesso!'));
        } else {
            $this->dispatch('notify', type: 'error', message: $result['error'] ?? 'Falha no reenvio');
        }

        $this->loadPendingSubmissions();
    }

    public function runApiOperation(): void
    {
        $this->ensureTenantAccess();
        $this->validate(GestaoAgt::regrasDaConsulta());

        $this->apiOperationResult = $this->gestao()->consultar($this->apiOperation, [
            'apiRequestId' => $this->apiRequestId,
            'apiDocumentNo' => $this->apiDocumentNo,
            'apiDateFrom' => $this->apiDateFrom,
            'apiDateTo' => $this->apiDateTo,
        ], $this->ambienteActual());

        $this->loadLogs();
    }

    public function refreshReport()
    {
        if ($this->currentTenantId) {
            $this->ensureTenantAccess();
        }
        $this->checkStatus();
        $this->dispatch('notify', type: 'success', message: __('Relatório atualizado!'));
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.invoicing.agt-settings', [
            'series' => $this->currentTenantId ? $this->gestao()->series($this->ambienteActual()) : collect(),
            'caeCodes' => GestaoAgt::classesCae(),
            'hasTenant' => (bool) $this->currentTenantId,
            'currentTenant' => $this->currentTenantId ? Tenant::find($this->currentTenantId) : null,
            'availableTenants' => $this->isAdmin
                ? Tenant::orderBy('name')->get(['id', 'name', 'nif'])
                : collect(),
        ]);
    }
}
