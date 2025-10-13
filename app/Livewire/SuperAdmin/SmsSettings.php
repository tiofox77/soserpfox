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
    
    public $provider = 'd7networks';
    public $api_url = 'https://api.d7networks.com/messages/v1/send';
    public $api_token = '';
    public $sender_id = 'SOS ERP';
    public $report_url = '';
    public $is_active = true;
    
    // Test SMS
    public $test_phone = '';
    public $test_message = '';
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
            $this->api_token = $setting->api_token;
            $this->sender_id = $setting->sender_id;
            $this->report_url = $setting->report_url ?? '';
            $this->is_active = $setting->is_active;
        }
    }

    public function save()
    {
        $this->validate([
            'provider' => 'required|string',
            'api_url' => 'required|url',
            'api_token' => 'required|string',
            'sender_id' => 'required|string|max:11',
            'report_url' => 'nullable|url',
        ]);

        SmsSetting::updateOrCreate(
            ['tenant_id' => null],
            [
                'provider' => $this->provider,
                'api_url' => $this->api_url,
                'api_token' => $this->api_token,
                'sender_id' => $this->sender_id,
                'report_url' => $this->report_url,
                'is_active' => $this->is_active,
            ]
        );

        $this->dispatch('success', message: '✅ Configurações SMS salvas com sucesso!');
    }

    public function openTestModal()
    {
        $this->showTestModal = true;
        $this->test_message = 'Teste do SOS ERP - ' . now()->format('d/m/Y H:i:s');
    }

    public function sendTestSms()
    {
        $this->validate([
            'test_phone' => 'required|string',
            'test_message' => 'required|string',
        ]);

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

    public function render()
    {
        $logs = SmsLog::with('user', 'tenant')
                      ->orderBy('id', 'desc')
                      ->paginate(20);

        $templates = SmsTemplate::whereNull('tenant_id')
                                ->orderBy('name')
                                ->get();

        $stats = [
            'total' => SmsLog::count(),
            'sent' => SmsLog::where('status', 'sent')->count(),
            'failed' => SmsLog::where('status', 'failed')->count(),
            'today' => SmsLog::whereDate('created_at', today())->count(),
        ];

        return view('livewire.super-admin.sms-settings', compact('logs', 'templates', 'stats'));
    }
}
