<?php

namespace App\Livewire\SuperAdmin;

use App\Models\SmsSetting;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

#[Layout('layouts.superadmin')]
#[Title('Configurações SMS')]
class SmsSettings extends Component
{
    use WithPagination;

    public $activeTab = 'settings'; // settings, templates, logs
    public $logGateway = '';
    public $logStatus = '';
    public $logType = '';
    public $logSearch = '';
    public $logDateFrom = '';
    public $logDateTo = '';
    
    public $provider = 'd7networks';
    public $api_url = 'https://api.d7networks.com/messages/v1/send';
    public $api_token = '';
    public bool $apiTokenGuardado = false;
    public $telco_api_key_qas = '';
    public bool $telcoQasKeyGuardada = false;
    public $sender_id = 'SOS ERP';
    public $telco_application = 'soserp_prd';
    public $report_url = '';
    public $is_active = true;
    
    // Test SMS
    public $test_phone = '';
    public $test_message = '';
    public $test_template_id = '';
    public $showTestModal = false;
    
    // Template editing
    public $showTemplateModal = false;
    public $editingTemplateId = null;
    public $template_name = '';
    public $template_slug = '';
    public $template_content = '';
    public $template_description = '';
    public $template_is_active = true;

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $setting = SmsSetting::whereNull('tenant_id')->first();
        
        if ($setting) {
            $this->provider = $setting->provider;
            $this->api_url = $setting->api_url;
            $this->apiTokenGuardado = filled($setting->api_token);
            $this->telcoQasKeyGuardada = filled($setting->telco_api_key_qas);
            $this->api_token = '';
            $this->sender_id = $setting->sender_id;
            $this->telco_application = data_get($setting->config, 'telco_application', 'soserp_prd');
            $this->report_url = $setting->report_url ?? '';
            $this->is_active = $setting->is_active;
        }
    }

    public function save()
    {
        $this->validate([
            'provider' => 'required|in:d7networks,telcosms',
            'api_url' => 'required|url',
            'api_token' => 'nullable|string',
            'telco_api_key_qas' => 'nullable|string',
            'sender_id' => 'nullable|string|max:11',
            'telco_application' => 'required_if:provider,telcosms|in:soserp_prd,soserp_qas',
            'report_url' => 'nullable|url',
        ]);

        $existing = SmsSetting::whereNull('tenant_id')->first();
        if (blank($this->api_token) && blank($existing?->api_token)) {
            $this->addError('api_token', 'Indique o token D7 ou a chave api_key_app da TelcoSMS.');
            return;
        }
        if ($this->provider === 'telcosms' && $this->telco_application === 'soserp_qas'
            && blank($this->telco_api_key_qas) && blank($existing?->telco_api_key_qas)) {
            $this->addError('telco_api_key_qas', 'Indique a chave QAS da aplicação SOSERP.');
            return;
        }

        $data = [
            'provider' => $this->provider,
            'api_url' => $this->api_url,
            'sender_id' => $this->provider === 'telcosms' ? 'SOSERP' : $this->sender_id,
            'report_url' => $this->provider === 'telcosms' ? null : $this->report_url,
            'config' => $this->provider === 'telcosms'
                ? ['telco_application' => $this->telco_application, 'sender' => 'SOSERP']
                : ($existing?->config ?? []),
            'is_active' => $this->is_active,
        ];
        if (filled($this->api_token)) $data['api_token'] = trim($this->api_token);
        if ($this->provider === 'telcosms' && filled($this->telco_api_key_qas)) {
            $data['telco_api_key_qas'] = trim($this->telco_api_key_qas);
        }

        $setting = SmsSetting::updateOrCreate(
            ['tenant_id' => null],
            $data
        );

        $this->apiTokenGuardado = filled($setting->fresh()->api_token);
        $this->telcoQasKeyGuardada = filled($setting->fresh()->telco_api_key_qas);
        $this->api_token = '';
        $this->telco_api_key_qas = '';

        $this->dispatch('success', message: '✅ Configurações SMS salvas com sucesso!');
    }

    public function updatedProvider(string $provider): void
    {
        if ($provider === 'telcosms') {
            $this->api_url = 'https://www.telcosms.co.ao/api/v2/send_message';
            $this->sender_id = 'SOSERP';
            $this->telco_application = 'soserp_prd';
            $this->report_url = '';
        } elseif ($provider === 'd7networks') {
            $this->api_url = 'https://api.d7networks.com/messages/v1/send';
            $this->sender_id = $this->sender_id ?: 'SOS ERP';
        }
    }

    public function checkBalance(): void
    {
        if ($this->provider !== 'telcosms') return;
        $setting = SmsSetting::whereNull('tenant_id')->first();
        $key = $this->telco_application === 'soserp_qas'
            ? trim((string) $this->telco_api_key_qas)
            : trim((string) $this->api_token);
        if ($key === '') {
            $key = $this->telco_application === 'soserp_qas'
                ? (string) $setting?->telco_api_key_qas
                : (string) $setting?->api_token;
        }
        $result = (new \App\Services\TelcoSmsService($key))->checkBalance();
        if (($result['balance_unavailable'] ?? false) === true) {
            $ultimoEnvio = SmsLog::where('sender_id', 'SOSERP')->where('status', 'sent')->latest('sent_at')->first();
            $message = $ultimoEnvio
                ? 'Gateway TelcoSMS operacional. O fornecedor não disponibilizou o saldo neste momento (HTTP '
                    . ($result['status'] ?? 500) . '). Último SMS aceite em ' . optional($ultimoEnvio->sent_at)->format('d/m/Y H:i') . '.'
                : 'A TelcoSMS não disponibilizou o saldo neste momento (HTTP ' . ($result['status'] ?? 500)
                    . '). Isto não confirma falha da chave; envie um SMS de teste para validar o gateway.';
            $this->dispatch('warning', message: $message);
            return;
        }
        $message = $result['message'];
        if ($result['success'] && ($result['balance'] ?? null) !== null) $message .= ' Saldo: ' . $result['balance'];
        $this->dispatch($result['success'] ? 'success' : 'error', message: $message);
    }

    public function openTestModal()
    {
        $this->showTestModal = true;
        $template = SmsTemplate::whereNull('tenant_id')->where('slug', 'test')->where('is_active', true)->first();
        $this->test_template_id = $template?->id ?? '';
        $this->test_message = $template
            ? $template->render(['app_name' => config('app.name', 'SOS ERP'), 'test_message' => now()->format('d/m/Y H:i:s')])
            : 'Teste do SOS ERP - ' . now()->format('d/m/Y H:i:s');
    }

    public function updatedTestTemplateId($id): void
    {
        $template = SmsTemplate::whereNull('tenant_id')->where('is_active', true)->find($id);
        if ($template) {
            $this->test_message = $template->render([
                'app_name' => config('app.name', 'SOS ERP'),
                'test_message' => now()->format('d/m/Y H:i:s'),
                'tenant_name' => 'Empresa de Teste',
                'plan_name' => 'Plano de Teste',
                'days_remaining' => 5,
                'app_url' => config('app.url'),
                'user_name' => 'Utilizador de Teste',
                'user_email' => 'teste@exemplo.ao',
                'user_password' => '********',
            ]);
        }
    }

    public function sendTestSms()
    {
        $this->validate([
            'test_phone' => 'required|string',
            'test_message' => 'required|string',
        ]);

        // O fornecedor escolhido no formulário passa a ser o padrão ANTES do
        // teste. Antes, selecionar TelcoSMS e testar sem um clique separado em
        // Guardar ainda usava as credenciais D7 antigas da base.
        $this->save();
        if ($this->getErrorBag()->isNotEmpty()) return;

        try {
            $smsService = new SmsService();
            $result = $smsService->send($this->test_phone, $this->test_message, 'test', auth()->id(), null);

            if ($result['success']) {
                $this->dispatch('success', message: '✅ SMS de teste enviado com sucesso!');
                $this->showTestModal = false;
                $this->test_phone = '';
                $this->test_message = '';
            } else {
                $this->dispatch('error', message: '❌ Erro: ' . ($result['error'] ?? 'Desconhecido'));
            }
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ Erro: ' . $e->getMessage());
        }
    }

    public function editTemplate($id)
    {
        $template = SmsTemplate::findOrFail($id);
        
        $this->editingTemplateId = $template->id;
        $this->template_name = $template->name;
        $this->template_slug = $template->slug;
        $this->template_content = $template->content;
        $this->template_description = $template->description;
        $this->template_is_active = $template->is_active;
        
        $this->showTemplateModal = true;
    }

    public function saveTemplate()
    {
        $this->validate([
            'template_name' => 'required|string',
            'template_content' => 'required|string',
            'template_description' => 'nullable|string',
        ]);

        if ($this->editingTemplateId) {
            $template = SmsTemplate::findOrFail($this->editingTemplateId);
            $template->update([
                'name' => $this->template_name,
                'content' => $this->template_content,
                'description' => $this->template_description,
                'is_active' => $this->template_is_active,
            ]);

            $this->dispatch('success', message: '✅ Template atualizado com sucesso!');
        }

        $this->showTemplateModal = false;
        $this->resetTemplateForm();
    }

    private function resetTemplateForm()
    {
        $this->editingTemplateId = null;
        $this->template_name = '';
        $this->template_slug = '';
        $this->template_content = '';
        $this->template_description = '';
        $this->template_is_active = true;
    }

    public function clearLogFilters(): void
    {
        $this->reset(['logGateway', 'logStatus', 'logType', 'logSearch', 'logDateFrom', 'logDateTo']);
        $this->resetPage();
    }

    public function updatedLogGateway(): void { $this->resetPage(); }
    public function updatedLogStatus(): void { $this->resetPage(); }
    public function updatedLogType(): void { $this->resetPage(); }
    public function updatedLogSearch(): void { $this->resetPage(); }
    public function updatedLogDateFrom(): void { $this->resetPage(); }
    public function updatedLogDateTo(): void { $this->resetPage(); }

    public function render()
    {
        $logs = SmsLog::with('user', 'tenant')
            ->when($this->logGateway === 'telcosms', fn ($q) => $q->where(function ($x) {
                $x->where('gateway', 'telcosms')->orWhere(fn ($old) => $old->whereNull('gateway')->where('sender_id', 'SOSERP'));
            }))
            ->when($this->logGateway === 'd7networks', fn ($q) => $q->where(function ($x) {
                $x->where('gateway', 'd7networks')->orWhere(fn ($old) => $old->whereNull('gateway')->where('sender_id', '!=', 'SOSERP'));
            }))
            ->when($this->logStatus !== '', fn ($q) => $q->where('status', $this->logStatus))
            ->when($this->logType !== '', fn ($q) => $q->where('type', $this->logType))
            ->when(trim($this->logSearch) !== '', fn ($q) => $q->where(function ($x) {
                $term = '%' . trim($this->logSearch) . '%';
                $x->where('recipient', 'like', $term)->orWhere('message', 'like', $term);
            }))
            ->when($this->logDateFrom !== '', fn ($q) => $q->whereDate('sent_at', '>=', $this->logDateFrom))
            ->when($this->logDateTo !== '', fn ($q) => $q->whereDate('sent_at', '<=', $this->logDateTo))
            ->orderByDesc('id')
            ->paginate(20);

        $logTypes = SmsLog::whereNotNull('type')->where('type', '!=', '')->distinct()->orderBy('type')->pluck('type');

        $templates = SmsTemplate::whereNull('tenant_id')
                                ->orderBy('name')
                                ->get();

        $stats = [
            'total' => SmsLog::count(),
            'sent' => SmsLog::where('status', 'sent')->count(),
            'failed' => SmsLog::where('status', 'failed')->count(),
            'today' => SmsLog::whereDate('created_at', today())->count(),
        ];

        return view('livewire.super-admin.sms-settings', compact('logs', 'logTypes', 'templates', 'stats'));
    }
}
