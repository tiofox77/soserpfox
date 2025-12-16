<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\AGT\AGTSubmission;
use App\Models\AGT\AGTCommunicationLog;
use App\Services\AGT\AGTService;
use App\Services\AGT\AGTClient;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Crypt;

#[Layout('layouts.app')]
#[Title('Configurações AGT')]
class AGTSettings extends Component
{
    // Configurações API
    public string $agt_environment = 'sandbox';
    public string $agt_api_base_url = '';
    public string $agt_client_id = '';
    public string $agt_client_secret = '';
    public string $agt_software_certificate = '';
    public bool $agt_auto_submit = false;
    public bool $agt_require_validation = true;

    // Estado
    public bool $isConnected = false;
    public bool $hasKeys = false;
    public array $complianceReport = [];
    public array $connectionTest = [];
    public string $activeTab = 'config';

    // Logs
    public $recentLogs = [];
    public $pendingSubmissions = [];

    public function mount()
    {
        $this->loadSettings();
        $this->checkStatus();
    }

    private function loadSettings()
    {
        $settings = InvoicingSettings::where('tenant_id', activeTenantId())->first();

        if ($settings) {
            $this->agt_environment = $settings->agt_environment ?? 'sandbox';
            $this->agt_api_base_url = $settings->agt_api_base_url ?? '';
            $this->agt_software_certificate = $settings->agt_software_certificate ?? $settings->saft_software_cert ?? '';
            $this->agt_auto_submit = $settings->agt_auto_submit ?? false;
            $this->agt_require_validation = $settings->agt_require_validation ?? true;

            // Decriptar credenciais para mostrar (mascaradas)
            if ($settings->agt_client_id) {
                try {
                    $this->agt_client_id = Crypt::decryptString($settings->agt_client_id);
                } catch (\Exception $e) {
                    $this->agt_client_id = $settings->agt_client_id;
                }
            }
        }
    }

    private function checkStatus()
    {
        $this->hasKeys = \App\Helpers\SAFTHelper::keysExist();
        
        $agtService = new AGTService(activeTenantId());
        $this->complianceReport = $agtService->getComplianceReport();
        
        $this->loadLogs();
        $this->loadPendingSubmissions();
    }

    private function loadLogs()
    {
        $this->recentLogs = AGTCommunicationLog::where('tenant_id', activeTenantId())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function loadPendingSubmissions()
    {
        $this->pendingSubmissions = AGTSubmission::where('tenant_id', activeTenantId())
            ->whereIn('status', ['pending', 'submitted'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function save()
    {
        $this->validate([
            'agt_environment' => 'required|in:sandbox,production',
            'agt_software_certificate' => 'nullable|string|max:50',
        ]);

        $settings = InvoicingSettings::firstOrCreate(
            ['tenant_id' => activeTenantId()],
            ['default_currency' => 'AOA']
        );

        $updateData = [
            'agt_environment' => $this->agt_environment,
            'agt_api_base_url' => $this->agt_api_base_url ?: null,
            'agt_software_certificate' => $this->agt_software_certificate,
            'agt_auto_submit' => $this->agt_auto_submit,
            'agt_require_validation' => $this->agt_require_validation,
        ];

        // Encriptar credenciais se fornecidas
        if ($this->agt_client_id && $this->agt_client_id !== '********') {
            $updateData['agt_client_id'] = Crypt::encryptString($this->agt_client_id);
        }

        if ($this->agt_client_secret && $this->agt_client_secret !== '********') {
            $updateData['agt_client_secret'] = Crypt::encryptString($this->agt_client_secret);
        }

        $settings->update($updateData);

        $this->dispatch('notify', type: 'success', message: 'Configurações AGT guardadas com sucesso!');
    }

    public function testConnection()
    {
        try {
            $client = new AGTClient(activeTenantId());
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
        try {
            $agtService = new AGTService(activeTenantId());
            $result = $agtService->syncAllSeries();

            if ($result['success'] > 0) {
                $this->dispatch('notify', type: 'success', 
                    message: "{$result['success']} série(s) sincronizada(s) com sucesso!");
            }

            if ($result['failed'] > 0) {
                $this->dispatch('notify', type: 'warning', 
                    message: "{$result['failed']} série(s) falharam na sincronização.");
            }

            $this->checkStatus();

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro: ' . $e->getMessage());
        }
    }

    public function retrySubmission(int $submissionId)
    {
        try {
            $submission = AGTSubmission::find($submissionId);
            
            if (!$submission || $submission->tenant_id !== activeTenantId()) {
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

            $agtService = new AGTService(activeTenantId());
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

    public function refreshReport()
    {
        $this->checkStatus();
        $this->dispatch('notify', type: 'success', message: 'Relatório atualizado!');
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $series = InvoicingSeries::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        return view('livewire.invoicing.agt-settings', [
            'series' => $series,
        ]);
    }
}
