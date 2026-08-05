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
    /**
     * Ambiente que este ecrã está a CONFIGURAR.
     *
     * Não é o ambiente activo de ninguém: cada empresa tem o seu, e é ele que
     * decide qual destes conjuntos se usa ao assinar e ao submeter. Aqui só se
     * escolhe qual dos dois se está a preencher — trocar o seletor não muda
     * nada em produção.
     */
    public string $produtorAmbiente = 'sandbox';

    /** As credenciais deste ambiente são as próprias, ou as legadas? */
    public bool $credenciaisProprias = false;

    /**
     * Username que este ambiente está a herdar das partilhadas.
     *
     * Não vai no campo — vai no placeholder. Pô-lo no campo fazia parecer que
     * o ambiente já estava configurado; escondê-lo por completo fazia parecer
     * que se tinha perdido. Continua a ser o que autentica.
     */
    public string $usernameHerdado = '';

    /**
     * Estado da chave RSA do produtor — só leitura.
     *
     * A chave já existe e é gerada fora daqui; o ecrã serve para confirmar qual
     * está instalada em cada ambiente, não para a substituir. Os campos de
     * colar o par foram removidos: convidavam a trocar, por engano, a chave que
     * assina os documentos de todas as empresas ao mesmo tempo.
     */
    public bool $chavesProprias = false;
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
        $this->carregarEstadoProdutor();
    }

    /**
     * Estado da chave do produtor instalada neste ambiente.
     *
     * Nunca carrega a chave PRIVADA para o ecrã — só a impressão digital da
     * pública, para se confirmar qual está em uso sem a expor.
     */
    private function carregarEstadoChaveProdutor(): void
    {
        $ambiente = $this->ambienteProdutor();
        $caminho  = \App\Services\AGT\AGTProducerStore::publicKeyPath($ambiente);

        $this->hasProducerKeys = \App\Services\AGT\AGTProducerStore::temChaves($ambiente);
        $this->chavesProprias  = \App\Services\AGT\AGTProducerStore::temChavesProprias($ambiente);
        $this->producerKeyInfo = [];

        if (!$this->hasProducerKeys) {
            return;
        }

        try {
            $disco = \Illuminate\Support\Facades\Storage::disk('local');
            $pem = $disco->get($caminho);
            $recurso = openssl_pkey_get_public($pem);

            if ($recurso) {
                $detalhes = openssl_pkey_get_details($recurso);
                $this->producerKeyInfo = [
                    'bits'        => $detalhes['bits'] ?? null,
                    'tipo'        => ($detalhes['type'] ?? null) === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'outro',
                    // SHA-256 do PEM: identifica o par sem o revelar
                    'impressao'   => strtoupper(substr(hash('sha256', $pem), 0, 32)),
                    'actualizada' => date('d/m/Y H:i', $disco->lastModified($caminho)),
                    'caminho'     => $caminho,
                    'ambiente'    => $this->rotuloAmbiente($ambiente),
                    'propria'     => $this->chavesProprias,
                ];
            }
        } catch (\Throwable $e) {
            $this->producerKeyInfo = ['erro' => $e->getMessage()];
        }
    }


    public function saveAgtProducerCredentials(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $this->validate([
            'agt_basic_username' => 'required|string|max:255',
            // "Deixar vazio para manter" só vale quando há uma password PRÓPRIA
            // deste ambiente para manter. A do recurso partilhado não conta:
            // gravar só o username deixava-o a autenticar-se com o username de
            // um ambiente e a password do outro.
            'agt_basic_password' => $this->credenciaisProprias
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

        // Grava no conjunto do AMBIENTE seleccionado. A AGT entrega credenciais
        // diferentes para homologação e produção, e antes só havia um par: pôr
        // as de produção obrigava a apagar as de homologação, deixando quem
        // ainda testava sem forma de o fazer.
        $ambiente = $this->ambienteProdutor();
        $prefixo  = $ambiente === 'production' ? 'AGT_PRODUCTION_API' : 'AGT_SANDBOX_API';

        $updates = ["{$prefixo}_USERNAME" => $username];
        if ($this->agt_basic_password !== '') {
            $updates["{$prefixo}_PASSWORD"] = $this->agt_basic_password;
        }

        $this->writeEnvironmentValues($updates);
        Artisan::call('config:clear');

        config(["services.agt.{$ambiente}.username" => $updates["{$prefixo}_USERNAME"]]);
        if (isset($updates["{$prefixo}_PASSWORD"])) {
            config(["services.agt.{$ambiente}.password" => $updates["{$prefixo}_PASSWORD"]]);
        }

        $this->agt_basic_password = '';
        $this->carregarEstadoProdutor();

        session()->flash('message', 'Credenciais do produtor para ' . $this->rotuloAmbiente($ambiente) . ' actualizadas com sucesso.');
        session()->flash('message-type', 'success');
    }

    public function clearAgtProducerCredentials(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);

        $ambiente = $this->ambienteProdutor();
        $prefixo  = $ambiente === 'production' ? 'AGT_PRODUCTION_API' : 'AGT_SANDBOX_API';

        $this->writeEnvironmentValues([
            "{$prefixo}_USERNAME" => '',
            "{$prefixo}_PASSWORD" => '',
        ]);
        Artisan::call('config:clear');
        config([
            "services.agt.{$ambiente}.username" => null,
            "services.agt.{$ambiente}.password" => null,
        ]);

        $this->agt_basic_username = '';
        $this->agt_basic_password = '';
        $this->carregarEstadoProdutor();

        session()->flash('message', 'Credenciais do produtor para ' . $this->rotuloAmbiente($ambiente) . ' removidas.');
        session()->flash('message-type', 'success');
    }

    /** Ambiente que o ecrã está a configurar (não muda nada em produção). */
    private function ambienteProdutor(): string
    {
        return \App\Services\AGT\AGTProducerStore::normalizar($this->produtorAmbiente);
    }

    private function rotuloAmbiente(string $ambiente): string
    {
        return $ambiente === 'production' ? 'PRODUÇÃO' : 'homologação';
    }

    /** Trocar o seletor recarrega o estado do outro ambiente. */
    public function updatedProdutorAmbiente(): void
    {
        $this->carregarEstadoProdutor();
    }

    /**
     * Estado do ambiente seleccionado: credenciais e chaves.
     *
     * Nunca traz a palavra-passe nem a chave privada para o ecrã — só diz se
     * estão configuradas e se são as do ambiente ou as legadas.
     */
    private function carregarEstadoProdutor(): void
    {
        $ambiente = $this->ambienteProdutor();
        $loja     = \App\Services\AGT\AGTProducerStore::class;

        $credenciais = $loja::credenciais($ambiente);

        // Só o username PRÓPRIO do ambiente. Mostrar o do recurso partilhado
        // fazia o campo não mudar ao trocar de ambiente e, pior, exibia um
        // username de homologação debaixo do rótulo "Produção" — parecia
        // configurado quando não estava. Em falta, o campo fica vazio e o
        // aviso ao lado explica de onde vem o que está a ser usado.
        $this->agt_basic_username    = $credenciais['proprias'] ? $credenciais['username'] : '';
        $this->usernameHerdado       = $credenciais['proprias'] ? '' : $credenciais['username'];
        $this->hasGlobalCredentials  = $loja::temCredenciais($ambiente);
        $this->credenciaisProprias   = $credenciais['proprias'];

        $this->carregarEstadoChaveProdutor();
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
