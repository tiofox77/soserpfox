<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use App\Models\SoftwareSetting;
use App\Models\Tenant;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use App\Services\AGT\AGTClient;
use App\Helpers\SAFTHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SoftwareSettings extends Component
{
    public $activeModule = 'invoicing';

    // Credenciais globais emitidas pela AGT ao produtor SOS ERP.
    public string $agt_basic_username = '';
    public string $agt_basic_password = '';
    public bool $hasGlobalCredentials = false;
    public bool $showGlobalPassword = false;

    // ── Chave RSA do PRODUTOR ────────────────────────────────────────────────
    //
    // Assina a jwsSoftwareSignature de TODOS os payloads, de todas as empresas.
    // É a chave do SOS ERP enquanto produtor de software, não do contribuinte —
    // essa é por empresa e configura-se em Faturação › AGT.
    //
    // Até agora só existia por linha de comandos (agt:producer-key), o que
    // obrigava a acesso ao servidor para a instalar ou substituir.
    public string $producerPublicKey = '';
    public string $producerPrivateKey = '';
    public bool $hasProducerKeys = false;
    public array $producerKeyInfo = [];
    public ?int $selectedAgtTenantId = null;
    public string $agtTestEnvironment = 'sandbox';
    public array $agtTestResult = [];
    public string $agtApiOperation = 'listarFacturas';
    public string $agtApiRequestId = '';
    public string $agtApiDocumentNo = '';
    public string $agtApiDateFrom = '';
    public string $agtApiDateTo = '';
    public array $agtApiOperationResult = [];
    
    // Configurações do módulo de faturação
    public $block_delete_sales_invoice = false;
    public $block_delete_proforma = false;
    public $block_delete_receipt = false;
    public $block_delete_credit_note = false;
    // A nota de débito lia a chave da nota de crédito: são documentos distintos
    // e cada um precisa do seu próprio interruptor.
    public $block_delete_debit_note = false;
    public $block_delete_invoice_receipt = false;
    public $block_delete_pos_invoice = false;
    
    public function mount()
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);
        $this->loadSettings();
        $this->loadAgtProducerCredentials();
        $this->selectedAgtTenantId = Tenant::orderBy('name')->value('id');
        $this->agtApiDateFrom = now()->subDays(30)->format('Y-m-d');
        $this->agtApiDateTo = now()->format('Y-m-d');
    }
    
    public function loadSettings()
    {
        if ($this->activeModule === 'invoicing') {
            $this->block_delete_sales_invoice = SoftwareSetting::get('invoicing', 'block_delete_sales_invoice', false);
            $this->block_delete_proforma = SoftwareSetting::get('invoicing', 'block_delete_proforma', false);
            $this->block_delete_receipt = SoftwareSetting::get('invoicing', 'block_delete_receipt', false);
            $this->block_delete_credit_note = SoftwareSetting::get('invoicing', 'block_delete_credit_note', false);
            $this->block_delete_debit_note = SoftwareSetting::get('invoicing', 'block_delete_debit_note', false);
            $this->block_delete_invoice_receipt = SoftwareSetting::get('invoicing', 'block_delete_invoice_receipt', false);
            $this->block_delete_pos_invoice = SoftwareSetting::get('invoicing', 'block_delete_pos_invoice', false);
        }
    }
    
    public function switchModule($module)
    {
        $this->activeModule = $module;
        $this->loadSettings();
    }
    
    public function saveSettings()
    {
        try {
            if ($this->activeModule === 'invoicing') {
                SoftwareSetting::set('invoicing', 'block_delete_sales_invoice', $this->block_delete_sales_invoice);
                SoftwareSetting::set('invoicing', 'block_delete_proforma', $this->block_delete_proforma);
                SoftwareSetting::set('invoicing', 'block_delete_receipt', $this->block_delete_receipt);
                SoftwareSetting::set('invoicing', 'block_delete_credit_note', $this->block_delete_credit_note);
                SoftwareSetting::set('invoicing', 'block_delete_debit_note', $this->block_delete_debit_note);
                SoftwareSetting::set('invoicing', 'block_delete_invoice_receipt', $this->block_delete_invoice_receipt);
                SoftwareSetting::set('invoicing', 'block_delete_pos_invoice', $this->block_delete_pos_invoice);
            }
            
            // Limpar cache
            SoftwareSetting::clearCache();
            
            session()->flash('message', 'Configurações salvas com sucesso!');
            session()->flash('message-type', 'success');
            
        } catch (\Exception $e) {
            session()->flash('message', 'Erro ao salvar configurações: ' . $e->getMessage());
            session()->flash('message-type', 'error');
        }
    }
    
    public function resetSettings()
    {
        if ($this->activeModule === 'invoicing') {
            $this->block_delete_sales_invoice = false;
            $this->block_delete_proforma = false;
            $this->block_delete_receipt = false;
            $this->block_delete_credit_note = false;
            $this->block_delete_debit_note = false;
            $this->block_delete_invoice_receipt = false;
            $this->block_delete_pos_invoice = false;
        }
        
        session()->flash('message', 'Configurações resetadas. Clique em "Salvar" para aplicar.');
        session()->flash('message-type', 'info');
    }
    
    public function getTenantAgtStatusProperty(): array
    {
        $tenants = Tenant::orderBy('name')->get();
        $status = [];

        foreach ($tenants as $tenant) {
            $settings = InvoicingSettings::where('tenant_id', $tenant->id)->first();
            $seriesCount = InvoicingSeries::where('tenant_id', $tenant->id)->where('is_active', true)->count();

            $status[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'nif' => $tenant->nif ?? null,
                'environment' => $settings->agt_environment ?? 'sandbox',
                'api_configured' => !empty(config('services.agt.username')) && !empty(config('services.agt.password')),
                'series_count' => $seriesCount,
                'auto_submit' => $settings->agt_auto_submit ?? false,
                'contributor_key' => Storage::disk('local')->exists("agt/tenants/{$tenant->id}/private_key.pem")
                    && Storage::disk('local')->exists("agt/tenants/{$tenant->id}/public_key.pem"),
            ];
        }

        return $status;
    }

    public function getHasRsaKeysProperty(): bool
    {
        return SAFTHelper::keysExist();
    }

    private function loadAgtProducerCredentials(): void
    {
        $this->agt_basic_username = (string) config('services.agt.username', '');
        $this->hasGlobalCredentials = $this->agt_basic_username !== ''
            && !empty(config('services.agt.password'));

        $this->carregarEstadoChaveProdutor();
    }

    /**
     * Estado da chave do produtor. Nunca carrega a chave PRIVADA para o ecrã —
     * só a impressão digital, para se confirmar qual está instalada sem a expor.
     */
    private function carregarEstadoChaveProdutor(): void
    {
        $this->hasProducerKeys = SAFTHelper::keysExist();
        $this->producerKeyInfo = [];

        if (!$this->hasProducerKeys) {
            return;
        }

        try {
            $disco = \Illuminate\Support\Facades\Storage::disk('local');
            $pem = $disco->get('saft/public_key.pem');
            $recurso = openssl_pkey_get_public($pem);

            if ($recurso) {
                $detalhes = openssl_pkey_get_details($recurso);
                $this->producerKeyInfo = [
                    'bits'        => $detalhes['bits'] ?? null,
                    'tipo'        => ($detalhes['type'] ?? null) === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'outro',
                    // SHA-256 do DER da chave pública: identifica o par sem o revelar
                    'impressao'   => strtoupper(substr(hash('sha256', $pem), 0, 32)),
                    'actualizada' => date('d/m/Y H:i', $disco->lastModified('saft/public_key.pem')),
                ];
            }
        } catch (\Throwable $e) {
            $this->producerKeyInfo = ['erro' => $e->getMessage()];
        }
    }

    /**
     * Instala (ou substitui) o par RSA do produtor.
     *
     * Valida que é um par RSA válido E que a privada corresponde à pública
     * ANTES de gravar: uma chave trocada só se descobriria quando a AGT
     * começasse a recusar todos os documentos de todas as empresas.
     */
    public function saveProducerKeys(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $this->validate([
            'producerPublicKey'  => 'required|string',
            'producerPrivateKey' => 'required|string',
        ], [], [
            'producerPublicKey'  => 'chave pública',
            'producerPrivateKey' => 'chave privada',
        ]);

        $publica = trim($this->producerPublicKey);
        $privada = trim($this->producerPrivateKey);

        $recursoPublico = openssl_pkey_get_public($publica);
        if (!$recursoPublico) {
            $this->addError('producerPublicKey', 'Não é uma chave pública PEM válida.');
            return;
        }

        $recursoPrivado = openssl_pkey_get_private($privada);
        if (!$recursoPrivado) {
            $this->addError('producerPrivateKey', 'Não é uma chave privada PEM válida (se tiver palavra-passe, remova-a primeiro).');
            return;
        }

        // O par tem de casar: assinar com a privada e verificar com a pública.
        $amostra = 'sos-erp-verificacao-' . bin2hex(random_bytes(8));
        $assinatura = '';

        if (!openssl_sign($amostra, $assinatura, $recursoPrivado, OPENSSL_ALGO_SHA256)
            || openssl_verify($amostra, $assinatura, $recursoPublico, OPENSSL_ALGO_SHA256) !== 1) {
            $this->addError('producerPrivateKey', 'A chave privada não corresponde à pública. Cole o par completo.');
            return;
        }

        $detalhes = openssl_pkey_get_details($recursoPublico);
        if (($detalhes['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            $this->addError('producerPublicKey', 'A AGT exige RSA.');
            return;
        }
        if (($detalhes['bits'] ?? 0) < 2048) {
            $this->addError('producerPublicKey', 'A chave tem de ter pelo menos 2048 bits.');
            return;
        }

        $disco = \Illuminate\Support\Facades\Storage::disk('local');

        // Cópia da anterior: substituir a chave do produtor por engano deixaria
        // todas as empresas sem forma de comunicar com a AGT.
        if (SAFTHelper::keysExist()) {
            $carimbo = now()->format('Ymd_His');
            $disco->put("saft/backup/{$carimbo}_public_key.pem", $disco->get('saft/public_key.pem'));
            $disco->put("saft/backup/{$carimbo}_private_key.pem", $disco->get('saft/private_key.pem'));
        }

        $disco->put('saft/public_key.pem', $publica . "\n");
        $disco->put('saft/private_key.pem', $privada . "\n");

        \Illuminate\Support\Facades\Log::warning('AGT: chave do produtor substituída', [
            'user_id' => auth()->id(),
            'bits'    => $detalhes['bits'] ?? null,
            'ip'      => request()?->ip(),
        ]);

        $this->producerPublicKey = '';
        $this->producerPrivateKey = '';
        $this->carregarEstadoChaveProdutor();

        session()->flash('message', 'Chave do produtor instalada. A anterior ficou guardada em saft/backup/.');
        session()->flash('message-type', 'success');
    }

    public function saveAgtProducerCredentials(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $this->validate([
            'agt_basic_username' => 'required|string|max:255',
            'agt_basic_password' => $this->hasGlobalCredentials
                ? 'nullable|string|max:1000'
                : 'required|string|max:1000',
        ]);

        $username = trim($this->agt_basic_username);
        if ($username === '' || preg_match('/[\r\n]/', $username)) {
            $this->addError('agt_basic_username', 'Informe um username válido, sem quebras de linha.');
            return;
        }
        if ($this->agt_basic_password !== '' && preg_match('/[\r\n]/', $this->agt_basic_password)) {
            $this->addError('agt_basic_password', 'A password não pode conter quebras de linha.');
            return;
        }

        $updates = ['AGT_API_USERNAME' => $username];
        if ($this->agt_basic_password !== '') {
            $updates['AGT_API_PASSWORD'] = $this->agt_basic_password;
        }

        $this->writeEnvironmentValues($updates);
        Artisan::call('config:clear');
        config(['services.agt.username' => $updates['AGT_API_USERNAME']]);
        if (isset($updates['AGT_API_PASSWORD'])) {
            config(['services.agt.password' => $updates['AGT_API_PASSWORD']]);
        }

        $this->agt_basic_password = '';
        $this->hasGlobalCredentials = true;
        session()->flash('message', 'Credenciais globais do produtor actualizadas com sucesso.');
        session()->flash('message-type', 'success');
    }

    public function clearAgtProducerCredentials(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $this->writeEnvironmentValues([
            'AGT_API_USERNAME' => '',
            'AGT_API_PASSWORD' => '',
        ]);
        Artisan::call('config:clear');
        config([
            'services.agt.username' => null,
            'services.agt.password' => null,
        ]);

        $this->agt_basic_username = '';
        $this->agt_basic_password = '';
        $this->hasGlobalCredentials = false;
        session()->flash('message', 'Credenciais globais do produtor removidas.');
        session()->flash('message-type', 'success');
    }

    public function updatedSelectedAgtTenantId($tenantId): void
    {
        $settings = InvoicingSettings::where('tenant_id', (int) $tenantId)->first();
        $this->agtTestEnvironment = $settings?->agt_environment ?? 'sandbox';
        $this->agtTestResult = [];
        $this->agtApiOperationResult = [];
    }

    public function prepareAgtTenantTest(int $tenantId): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);
        abort_unless(Tenant::whereKey($tenantId)->exists(), 404);

        $this->selectedAgtTenantId = $tenantId;
        $this->updatedSelectedAgtTenantId($tenantId);
        $this->dispatch('agt-test-console-focus');
    }

    public function applyAgtEnvironmentToTenant(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $validated = $this->validate([
            'selectedAgtTenantId' => 'required|integer|exists:tenants,id',
            'agtTestEnvironment' => 'required|in:sandbox,production',
        ]);

        InvoicingSettings::firstOrCreate(
            ['tenant_id' => $validated['selectedAgtTenantId']],
            ['default_currency' => 'AOA']
        )->update(['agt_environment' => $validated['agtTestEnvironment']]);

        $this->agtTestResult = [];
        session()->flash(
            'message',
            'Ambiente AGT da empresa actualizado para '
                . ($validated['agtTestEnvironment'] === 'production' ? 'Produção' : 'Sandbox') . '.'
        );
        session()->flash('message-type', 'success');
    }

    public function testAgtConnection(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $validated = $this->validate([
            'selectedAgtTenantId' => 'required|integer|exists:tenants,id',
            'agtTestEnvironment' => 'required|in:sandbox,production',
        ]);

        $startedAt = microtime(true);

        try {
            $client = new AGTClient(
                (int) $validated['selectedAgtTenantId'],
                $validated['agtTestEnvironment']
            );
            $result = $client->testConnection();
            $this->agtTestResult = array_merge($result, [
                'tested_at' => now()->format('d/m/Y H:i:s'),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'tenant_id' => (int) $validated['selectedAgtTenantId'],
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->agtTestResult = [
                'success' => false,
                'error' => $e->getMessage(),
                'environment' => $validated['agtTestEnvironment'],
                'tested_at' => now()->format('d/m/Y H:i:s'),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ];
        }
    }

    public function getAgtReadinessProperty(): array
    {
        $tenant = $this->selectedAgtTenantId
            ? Tenant::find($this->selectedAgtTenantId)
            : null;
        $settings = $tenant
            ? InvoicingSettings::where('tenant_id', $tenant->id)->first()
            : null;

        return [
            'producer_credentials' => $this->hasGlobalCredentials,
            'producer_rsa' => SAFTHelper::keysExist(),
            'software_certificate' => filled(softwareSetting('invoicing', 'saft_software_cert')),
            'tenant_nif' => filled($tenant?->nif ?? $tenant?->tax_id),
            'contributor_rsa' => $tenant
                ? Storage::disk('local')->exists("agt/tenants/{$tenant->id}/private_key.pem")
                    && Storage::disk('local')->exists("agt/tenants/{$tenant->id}/public_key.pem")
                : false,
            'active_series' => $tenant
                ? InvoicingSeries::where('tenant_id', $tenant->id)->where('is_active', true)->exists()
                : false,
            'environment' => $settings?->agt_environment ?? 'sandbox',
        ];
    }

    public function runAgtApiOperation(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $validated = $this->validate([
            'selectedAgtTenantId' => 'required|integer|exists:tenants,id',
            'agtTestEnvironment' => 'required|in:sandbox,production',
            'agtApiOperation' => 'required|in:listarFacturas,consultarFactura,obterEstado',
            'agtApiRequestId' => 'required_if:agtApiOperation,obterEstado|nullable|string|max:100',
            'agtApiDocumentNo' => 'required_if:agtApiOperation,consultarFactura|nullable|string|max:100',
            'agtApiDateFrom' => 'required_if:agtApiOperation,listarFacturas|nullable|date',
            'agtApiDateTo' => 'required_if:agtApiOperation,listarFacturas|nullable|date|after_or_equal:agtApiDateFrom',
        ]);

        $startedAt = microtime(true);

        try {
            $client = new AGTClient(
                (int) $validated['selectedAgtTenantId'],
                $validated['agtTestEnvironment']
            );
            $result = match ($validated['agtApiOperation']) {
                'obterEstado' => $client->getStatus(trim((string) $validated['agtApiRequestId'])),
                'consultarFactura' => $client->getInvoice(trim((string) $validated['agtApiDocumentNo'])),
                default => $client->listInvoices([
                    'date_from' => $validated['agtApiDateFrom'],
                    'date_to' => $validated['agtApiDateTo'],
                ]),
            };

            $this->agtApiOperationResult = array_merge($result, [
                'operation' => $validated['agtApiOperation'],
                'environment' => $validated['agtTestEnvironment'],
                'tenant_id' => (int) $validated['selectedAgtTenantId'],
                'tested_at' => now()->format('d/m/Y H:i:s'),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->agtApiOperationResult = [
                'success' => false,
                'operation' => $validated['agtApiOperation'],
                'environment' => $validated['agtTestEnvironment'],
                'error' => $e->getMessage(),
                'tested_at' => now()->format('d/m/Y H:i:s'),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ];
        }
    }

    private function writeEnvironmentValues(array $updates): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('Não foi possível ler o ficheiro de configuração do sistema.');
        }

        foreach ($updates as $key => $value) {
            $encoded = $value === ''
                ? ''
                : '"' . addcslashes((string) $value, "\\\"") . '"';
            $line = $key . '=' . $encoded;

            if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $contents)) {
                $contents = preg_replace_callback(
                    '/^' . preg_quote($key, '/') . '=.*/m',
                    static fn () => $line,
                    $contents,
                    1
                );
            } else {
                $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
            }
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível guardar as credenciais globais.');
        }
    }

    public function render()
    {
        return view('livewire.super-admin.software-settings', [
            'tenantAgtStatus' => $this->tenantAgtStatus,
            'hasRsaKeys' => $this->hasRsaKeys,
            'agtReadiness' => $this->agtReadiness,
        ])->layout('layouts.superadmin');
    }
}
