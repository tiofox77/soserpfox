<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\AGT\AGTSubmission;
use App\Models\AGT\AGTCommunicationLog;
use App\Services\AGT\AGTService;
use App\Services\AGT\AGTClient;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Configurações AGT')]
class AGTSettings extends Component
{
    // Configurações API
    public string $agt_environment = 'sandbox';
    public bool $agt_auto_submit = false;

    /**
     * Código de actividade económica (CAE) da empresa.
     *
     * Vai em `eacCode` em cada documento do payload. Sem ele o mapper usava o
     * marcador '00000', que não identifica actividade nenhuma — a AGT espera
     * uma classe real da tabela CAE.
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
        if (!$tenantId || !\App\Models\Tenant::whereKey($tenantId)->exists()) {
            return null;
        }

        $user = auth()->user();
        if ($user?->isPlatformSuperAdmin()) {
            return $tenantId;
        }

        return $user?->tenants()->whereKey($tenantId)->exists() ? $tenantId : null;
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

        // Se não tem tenant, usa valores padrão
        if (!$this->currentTenantId) {
            return;
        }
        
        $settings = InvoicingSettings::where('tenant_id', $this->currentTenantId)->first();

        if ($settings) {
            $this->agt_environment = $settings->agt_environment ?? 'sandbox';
            $this->agt_auto_submit = $settings->agt_auto_submit ?? false;
            $this->agt_eac_code = $settings->agt_eac_code ?? '';
            $this->agt_require_validation = $settings->agt_require_validation ?? true;
        }

    }

    private function checkStatus()
    {
        // Chaves DO AMBIENTE seleccionado: o Portal do Contribuinte entrega
        // pares diferentes para homologação e para produção.
        $ambiente = $this->ambienteActual();
        $this->hasPublicKey = $this->currentTenantId && Storage::disk('local')->exists(
            \App\Services\AGT\AGTKeyStore::publicKeyPath((int) $this->currentTenantId, $ambiente)
        );
        $this->hasPrivateKey = $this->currentTenantId && Storage::disk('local')->exists(
            \App\Services\AGT\AGTKeyStore::privateKeyPath((int) $this->currentTenantId, $ambiente)
        );
        $this->hasKeys = $this->hasPublicKey && $this->hasPrivateKey;
        $this->hasGlobalCredentials = filled(config('services.agt.username'))
            && filled(config('services.agt.password'));
        
        // Só carregar relatório se tiver tenant ativo
        if ($this->currentTenantId) {
            $agtService = new AGTService($this->currentTenantId);
            // Ambiente do seletor, não o gravado — os cartões têm de mudar
            // assim que se troca, antes sequer de guardar.
            $this->complianceReport = $agtService->getComplianceReport($this->ambienteActual());
            $this->loadLogs();
            $this->loadPendingSubmissions();
        } else {
            // Para admin sem tenant, mostrar relatório vazio
            $this->complianceReport = [
                'tenant_id' => null,
                'generated_at' => now()->toDateTimeString(),
                'keys_configured' => $this->hasKeys,
                'api_configured' => false,
                'environment' => 'sandbox',
                'series' => ['total' => 0, 'registered' => 0, 'pending' => 0],
                'submissions' => ['total' => 0, 'pending' => 0, 'submitted' => 0, 'validated' => 0, 'rejected' => 0],
                'invoices_30_days' => ['total' => 0, 'with_hash' => 0, 'with_jws' => 0, 'with_atcud' => 0, 'agt_validated' => 0],
            ];
            $this->recentLogs = [];
            $this->pendingSubmissions = [];
        }
    }

    private function loadLogs()
    {
        if (!$this->currentTenantId) {
            $this->recentLogs = [];
            return;
        }
        
        // Só o ambiente seleccionado: homologação e produção são universos
        // separados e misturá-los faz um documento de teste passar por real.
        $this->recentLogs = AGTCommunicationLog::where('tenant_id', $this->currentTenantId)
            ->where('agt_environment', $this->ambienteActual())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function loadPendingSubmissions()
    {
        if (!$this->currentTenantId) {
            $this->pendingSubmissions = [];
            return;
        }
        
        // Histórico completo: documentos processados não podem "desaparecer"
        // quando passam de submitted para validated/rejected.
        $this->pendingSubmissions = AGTSubmission::where('tenant_id', $this->currentTenantId)
            ->where('agt_environment', $this->ambienteActual())
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Trocar o seletor de ambiente recarrega tudo o que é específico dele.
     * Sem isto, as submissões e os logs continuavam a mostrar os do ambiente
     * anterior — misturando homologação com produção.
     */
    public function updatedAgtEnvironment(): void
    {
        // checkStatus() recarrega os cartões do topo E o estado das chaves —
        // cada ambiente tem o seu par.
        $this->checkStatus();
        $this->loadPendingSubmissions();
        $this->loadLogs();
    }

    /**
     * Ambiente seleccionado no ecrã. Usa-se a propriedade do formulário (e não
     * o valor gravado) para a lista mudar mal se troca o seletor, antes sequer
     * de guardar.
     */
    private function ambienteActual(): string
    {
        return in_array($this->agt_environment, ['sandbox', 'production'], true)
            ? $this->agt_environment
            : 'sandbox';
    }

    public function refreshSubmissionStatuses(): void
    {
        $this->ensureTenantAccess();
        $service = new \App\Services\AGT\QueryService(
            InvoicingSettings::forTenant($this->currentTenantId)
        );
        $updated = 0;

        $submissions = AGTSubmission::where('tenant_id', $this->currentTenantId)
            ->whereIn('status', ['pending', 'submitted'])
            ->whereNotNull('agt_reference')
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($submissions as $submission) {
            try {
                $result = $service->consultByRequestId($submission->agt_reference);
                $code = (string) ($result['resultCode'] ?? '');
                $body = $result['response'] ?? [];

                if ($code === '0') {
                    $submission->markAsValidated($submission->agt_reference, $submission->atcud, $body);
                    $updated++;
                } elseif ($code === '2') {
                    $errors = collect($body['documentStatusList'] ?? [])
                        ->flatMap(fn ($row) => $row['errorList'] ?? [])
                        ->filter(fn ($error) => is_array($error) && !empty($error['descriptionError']));
                    $submission->markAsRejected(
                        'RESULT_CODE_2',
                        $errors->pluck('descriptionError')->implode('; ') ?: 'Documento rejeitado pela AGT.',
                        $body
                    );
                    $updated++;
                }
            } catch (\Throwable $e) {
                \Log::warning('AGTSettings: falha ao actualizar submissao', [
                    'tenant_id' => $this->currentTenantId,
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->loadPendingSubmissions();
        $this->checkStatus();
        $this->dispatch(
            'notify',
            type: 'success',
            message: $updated
                ? "{$updated} estado(s) actualizado(s) com a AGT."
                : 'Estados consultados. Nenhuma alteracao encontrada.'
        );
    }

    public function save()
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: 'Selecione um tenant para guardar configurações.');
            return;
        }
        $this->ensureTenantAccess();
        
        $this->validate([
            'agt_environment' => 'required|in:sandbox,production',
        ]);

        $settings = InvoicingSettings::firstOrCreate(
            ['tenant_id' => $this->currentTenantId],
            ['default_currency' => 'AOA']
        );

        $updateData = [
            'agt_environment' => $this->agt_environment,
            'agt_auto_submit' => $this->agt_auto_submit,
            // CAE: vazio grava NULL, para o mapper cair no marcador em vez de ''
            'agt_eac_code' => $this->agt_eac_code ?: null,
            'agt_require_validation' => $this->agt_require_validation,
        ];

        $settings->update($updateData);

        $this->dispatch('notify', type: 'success', message: 'Configurações AGT guardadas com sucesso!');
    }

    public function saveContributorKeys(): void
    {
        $this->ensureTenantAccess();
        abort_unless(
            auth()->user()?->isPlatformSuperAdmin()
                || auth()->user()?->can('invoicing.agt.edit'),
            403
        );

        $this->validate([
            'contributorPublicKey' => 'required|string|max:20000',
            'contributorPrivateKey' => 'required|string|max:20000',
        ]);

        $publicKey = trim($this->contributorPublicKey) . PHP_EOL;
        $privateKey = trim($this->contributorPrivateKey) . PHP_EOL;
        $publicResource = @openssl_pkey_get_public($publicKey);
        $privateResource = @openssl_pkey_get_private($privateKey);

        if (!$publicResource) {
            $this->addError('contributorPublicKey', 'A chave pública não é um PEM RSA válido.');
            return;
        }
        if (!$privateResource) {
            $this->addError('contributorPrivateKey', 'A chave privada não é um PEM RSA válido ou está protegida por password.');
            return;
        }

        $publicDetails = openssl_pkey_get_details($publicResource);
        $privateDetails = openssl_pkey_get_details($privateResource);
        if (
            empty($publicDetails['rsa']['n'])
            || empty($privateDetails['rsa']['n'])
            || !hash_equals($publicDetails['rsa']['n'], $privateDetails['rsa']['n'])
        ) {
            $this->addError('contributorPrivateKey', 'As chaves pública e privada não pertencem ao mesmo par RSA.');
            return;
        }

        // Guardar no ambiente seleccionado, para não sobrepor o outro par.
        $ambiente = $this->ambienteActual();
        \App\Services\AGT\AGTKeyStore::store(
            (int) $this->currentTenantId, $publicKey, $privateKey, $ambiente
        );

        $this->contributorPublicKey = '';
        $this->contributorPrivateKey = '';
        $this->checkStatus();
        $rotulo = $ambiente === 'production' ? 'Produção' : 'Homologação';
        $this->dispatch('notify', type: 'success',
            message: "Par de chaves guardado para {$rotulo}. O outro ambiente não foi alterado.");
    }

    public function removeContributorKeys(): void
    {
        $this->ensureTenantAccess();
        abort_unless(
            auth()->user()?->isPlatformSuperAdmin()
                || auth()->user()?->can('invoicing.agt.edit'),
            403
        );

        // Só as do ambiente seleccionado — o outro par tem de sobreviver.
        $ambiente = $this->ambienteActual();
        $dir = \App\Services\AGT\AGTKeyStore::directory((int) $this->currentTenantId, $ambiente);
        Storage::disk('local')->delete(["{$dir}/public_key.pem", "{$dir}/private_key.pem"]);

        // Legado (sem ambiente): só se apaga quando se está no ambiente a que
        // essas chaves pertenciam, que a migração assumiu ser homologação.
        if ($ambiente === 'sandbox') {
            $legado = \App\Services\AGT\AGTKeyStore::legacyDirectory((int) $this->currentTenantId);
            Storage::disk('local')->delete(["{$legado}/public_key.pem", "{$legado}/private_key.pem"]);
        }

        $this->contributorPublicKey = '';
        $this->contributorPrivateKey = '';
        $this->checkStatus();
        $rotulo = $ambiente === 'production' ? 'Produção' : 'Homologação';
        $this->dispatch('notify', type: 'success',
            message: "Chaves de {$rotulo} removidas. O outro ambiente não foi alterado.");
    }

    public function testConnection()
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: 'Selecione um tenant para testar a conexão.');
            return;
        }
        $this->ensureTenantAccess();

        $missing = [];
        if (!$this->hasGlobalCredentials) {
            $missing[] = 'credenciais globais do produtor';
        }
        if (!$this->hasPublicKey) {
            $missing[] = 'chave pública do Portal AGT';
        }
        if (!$this->hasPrivateKey) {
            $missing[] = 'chave privada do Portal AGT';
        }
        if ($missing !== []) {
            $message = 'Não foi enviado nenhum pedido à AGT. Falta configurar: ' . implode(', ', $missing) . '.';
            $this->syncResult = [
                'total' => 0,
                'success' => 0,
                'failed' => 1,
                'error' => $message,
                'details' => [],
            ];
            $this->dispatch('notify', type: 'error', message: $message);
            return;
        }
        
        try {
            $client = new AGTClient($this->currentTenantId);
            $this->connectionTest = $client->testConnection();
            $this->isConnected = $this->connectionTest['success'] ?? false;

            if ($this->isConnected) {
                $this->dispatch('notify', type: 'success', message: 'Conexão estabelecida com sucesso!');
            } else {
                $this->dispatch('notify', type: 'error', message: $this->connectionTest['error'] ?? 'Falha na conexão');
            }
        } catch (\Exception $e) {
            $this->connectionTest = ['success' => false, 'error' => $e->getMessage()];
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function syncSeries()
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: 'Selecione um tenant para sincronizar séries.');
            return;
        }
        $this->ensureTenantAccess();

        $missing = [];
        if (!$this->hasGlobalCredentials) {
            $missing[] = 'credenciais globais do produtor';
        }
        if (!$this->hasPublicKey) {
            $missing[] = 'chave pública do Portal AGT';
        }
        if (!$this->hasPrivateKey) {
            $missing[] = 'chave privada do Portal AGT';
        }
        if ($missing !== []) {
            $message = 'Não foi enviado nenhum pedido à AGT. Falta configurar: ' . implode(', ', $missing) . '.';
            $this->syncResult = [
                'total' => 0,
                'success' => 0,
                'failed' => 1,
                'error' => $message,
                'details' => [],
            ];
            $this->dispatch('notify', type: 'error', message: $message);
            return;
        }
        
        try {
            $agtService = new AGTService($this->currentTenantId);
            $result = $agtService->syncAllSeries();
            $this->syncResult = $result;

            if (($result['total'] ?? 0) === 0) {
                $this->dispatch('notify', type: 'info',
                    message: 'Não existem séries activas pendentes de sincronização.');
            } elseif ($result['success'] > 0 && $result['failed'] === 0) {
                $this->dispatch('notify', type: 'success', 
                    message: "{$result['success']} série(s) sincronizada(s) com sucesso!");
            } elseif ($result['failed'] > 0) {
                $this->dispatch('notify', type: 'warning', 
                    message: "{$result['success']} sincronizada(s); {$result['failed']} falharam.");
            }

            $this->checkStatus();

        } catch (\Exception $e) {
            $this->syncResult = [
                'total' => 0,
                'success' => 0,
                'failed' => 1,
                'error' => $e->getMessage(),
                'details' => [],
            ];
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function retrySubmission(int $submissionId)
    {
        if (!$this->currentTenantId) {
            $this->dispatch('notify', type: 'error', message: 'Selecione um tenant para reenviar.');
            return;
        }
        $this->ensureTenantAccess();
        
        try {
            $submission = AGTSubmission::find($submissionId);
            
            if (!$submission || (int) $submission->tenant_id !== (int) $this->currentTenantId) {
                $this->dispatch('notify', type: 'error', message: 'Submissão não encontrada');
                return;
            }

            if (!$submission->canRetry()) {
                $this->dispatch('notify', type: 'error', message: 'Máximo de tentativas atingido');
                return;
            }

            $document = $submission->document;
            if (!$document) {
                $this->dispatch('notify', type: 'error', message: 'Documento não encontrado');
                return;
            }

            $agtService = new AGTService($this->currentTenantId);
            $result = $agtService->submitToAGT($document);

            if ($result['success']) {
                $this->dispatch('notify', type: 'success', message: 'Documento reenviado com sucesso!');
            } else {
                $this->dispatch('notify', type: 'error', message: $result['error'] ?? 'Falha no reenvio');
            }

            $this->loadPendingSubmissions();

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function runApiOperation(): void
    {
        $this->ensureTenantAccess();
        $this->validate([
            'apiOperation' => 'required|in:listarFacturas,consultarFactura,obterEstado',
            'apiRequestId' => 'required_if:apiOperation,obterEstado|nullable|string|max:100',
            'apiDocumentNo' => 'required_if:apiOperation,consultarFactura|nullable|string|max:100',
            'apiDateFrom' => 'required_if:apiOperation,listarFacturas|nullable|date',
            'apiDateTo' => 'required_if:apiOperation,listarFacturas|nullable|date|after_or_equal:apiDateFrom',
        ]);

        $startedAt = microtime(true);

        try {
            $client = new AGTClient($this->currentTenantId);
            $result = match ($this->apiOperation) {
                'obterEstado' => $client->getStatus(trim($this->apiRequestId)),
                'consultarFactura' => $client->getInvoice(trim($this->apiDocumentNo)),
                default => $client->listInvoices([
                    'date_from' => $this->apiDateFrom,
                    'date_to' => $this->apiDateTo,
                ]),
            };

            $this->apiOperationResult = array_merge($result, [
                'operation' => $this->apiOperation,
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'tested_at' => now()->format('d/m/Y H:i:s'),
            ]);
            $this->loadLogs();
        } catch (\Throwable $e) {
            report($e);
            $this->apiOperationResult = [
                'success' => false,
                'operation' => $this->apiOperation,
                'error' => $e->getMessage(),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'tested_at' => now()->format('d/m/Y H:i:s'),
            ];
        }
    }

    public function refreshReport()
    {
        if ($this->currentTenantId) {
            $this->ensureTenantAccess();
        }
        $this->checkStatus();
        $this->dispatch('notify', type: 'success', message: 'Relatório atualizado!');
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        // Classes CAE (5 dígitos) — é o nível que a AGT espera em eacCode.
        $caeCodes = \Illuminate\Support\Facades\Cache::remember('agt_cae_classes', 3600, fn () =>
            \Illuminate\Support\Facades\DB::table('agt_cae_codes')
                ->where('is_active', true)
                ->where('level', 'class')
                ->orderBy('code')
                ->get(['code', 'description']));

        // Séries registadas noutro ambiente não são mostradas como registadas:
        // uma série de homologação não existe em produção.
        $series = $this->currentTenantId
            ? InvoicingSeries::where('tenant_id', $this->currentTenantId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('agt_series_id')
                      ->orWhere('agt_environment', $this->ambienteActual());
                })
                ->get()
            : collect();

        return view('livewire.invoicing.agt-settings', [
            'series' => $series,
            'caeCodes' => $caeCodes,
            'hasTenant' => (bool) $this->currentTenantId,
            'currentTenant' => $this->currentTenantId
                ? \App\Models\Tenant::find($this->currentTenantId)
                : null,
            'availableTenants' => $this->isAdmin
                ? \App\Models\Tenant::orderBy('name')->get(['id', 'name', 'nif'])
                : collect(),
        ]);
    }
}
