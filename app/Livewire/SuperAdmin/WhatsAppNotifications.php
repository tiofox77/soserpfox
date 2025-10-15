<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use App\Models\WhatsAppSetting;
use App\Services\WhatsAppService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class WhatsAppNotifications extends Component
{
    public $twilio_account_sid;
    public $twilio_auth_token;
    public $whatsapp_from_number;
    public $whatsapp_business_account_id;
    public $is_enabled = false;
    public $is_sandbox = true;
    public $templates = [];
    public $notification_settings = [];
    
    public $availableTemplates = [];
    public $testNumber = '';
    public $testMessage = '';
    public $connectionStatus = null;

    public function mount()
    {
        $settings = WhatsAppSetting::getSettings();
        
        $this->twilio_account_sid = $settings->twilio_account_sid;
        $this->twilio_auth_token = $settings->twilio_auth_token;
        $this->whatsapp_from_number = $settings->whatsapp_from_number;
        $this->whatsapp_business_account_id = $settings->whatsapp_business_account_id;
        $this->is_enabled = $settings->is_enabled;
        $this->is_sandbox = $settings->is_sandbox;
        $this->templates = $settings->templates ?? [];
        $this->notification_settings = $settings->notification_settings ?? $this->getDefaultNotifications();
    }

    public function getDefaultNotifications()
    {
        return [
            'salary_advance_approved' => false,
            'salary_advance_rejected' => false,
            'vacation_approved' => false,
            'vacation_rejected' => false,
            'payslip_ready' => false,
            'employee_created' => false,
        ];
    }

    public function save()
    {
        $this->validate([
            'twilio_account_sid' => 'nullable|string',
            'twilio_auth_token' => 'nullable|string',
            'whatsapp_from_number' => 'nullable|string',
            'whatsapp_business_account_id' => 'nullable|string',
        ]);

        $settings = WhatsAppSetting::getSettings();
        
        $settings->update([
            'twilio_account_sid' => $this->twilio_account_sid,
            'twilio_auth_token' => $this->twilio_auth_token,
            'whatsapp_from_number' => $this->whatsapp_from_number,
            'whatsapp_business_account_id' => $this->whatsapp_business_account_id,
            'is_enabled' => $this->is_enabled,
            'is_sandbox' => $this->is_sandbox,
            'templates' => $this->templates,
            'notification_settings' => $this->notification_settings,
        ]);

        session()->flash('success', 'Configurações WhatsApp salvas com sucesso!');
    }

    public function testConnection()
    {
        $service = new WhatsAppService();
        $result = $service->testConnection();
        
        $this->connectionStatus = $result;
        
        if ($result['success']) {
            session()->flash('success', $result['message']);
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function fetchTemplates()
    {
        $service = new WhatsAppService();
        $this->availableTemplates = $service->fetchTemplates();
        
        session()->flash('success', count($this->availableTemplates) . ' templates encontrados!');
    }

    public function addTemplate($template)
    {
        if (!collect($this->templates)->contains('sid', $template['sid'])) {
            $this->templates[] = $template;
        }
    }

    public function removeTemplate($index)
    {
        unset($this->templates[$index]);
        $this->templates = array_values($this->templates);
    }

    public function sendTestMessage()
    {
        $this->validate([
            'testNumber' => 'required|string',
            'testMessage' => 'required|string',
        ]);

        $service = new WhatsAppService();
        $result = $service->sendMessage($this->testNumber, $this->testMessage);
        
        if ($result) {
            session()->flash('success', 'Mensagem de teste enviada! SID: ' . $result);
            $this->testNumber = '';
            $this->testMessage = '';
        } else {
            session()->flash('error', 'Falha ao enviar mensagem de teste');
        }
    }

    public function render()
    {
        return view('livewire.super-admin.whats-app-notifications');
    }
}
