<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\TenantNotificationSetting;
use App\Services\WhatsAppService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class NotificationSettings extends Component
{
    public $activeTab = 'dashboard';
    
    // Email Settings
    public $email_enabled = true;
    public $smtp_host;
    public $smtp_port;
    public $smtp_username;
    public $smtp_password;
    public $smtp_encryption = 'tls';
    public $from_email;
    public $from_name;
    public $email_notifications = [];
    
    // SMS Settings
    public $sms_enabled = false;
    public $sms_provider = '';
    public $sms_account_sid;
    public $sms_auth_token;
    public $sms_from_number;
    public $sms_api_token; // D7 Networks
    public $sms_sender_id; // D7 Networks
    public $sms_notifications = [];
    public $sms_notification_templates = []; // Template ID para cada tipo SMS
    
    // WhatsApp Settings
    public $whatsapp_enabled = false;
    public $whatsapp_provider = 'twilio';
    public $whatsapp_account_sid;
    public $whatsapp_auth_token;
    public $whatsapp_from_number;
    public $whatsapp_business_account_id;
    public $whatsapp_sandbox = true;
    public $whatsapp_notifications = [];
    public $whatsapp_templates = [];
    public $whatsapp_notification_templates = []; // Template ID para cada tipo WhatsApp
    public $email_notification_templates = []; // Template ID para cada tipo Email
    
    // Test
    public $testEmail = '';
    public $testPhone;
    public $testTemplateSid = '';
    public $testTemplateVariables = [];
    public $showVariablesModal = false;
    public $selectedTemplate = null;
    public $testMessage = 'Teste de notificação do SOSERP';
    
    public $availableWhatsAppTemplates = [];
    public $availableNotificationTemplates = []; // Templates do sistema

    public function mount()
    {
        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $settings = TenantNotificationSetting::getForTenant($tenantId);
        
        // Email
        $this->email_enabled = $settings->email_enabled;
        $this->smtp_host = $settings->smtp_host;
        $this->smtp_port = $settings->smtp_port ?? 587;
        $this->smtp_username = $settings->smtp_username;
        $this->smtp_password = $settings->smtp_password;
        $this->smtp_encryption = $settings->smtp_encryption ?? 'tls';
        $this->from_email = $settings->from_email;
        $this->from_name = $settings->from_name;
        $this->email_notifications = $settings->email_notifications ?? TenantNotificationSetting::getDefaultEmailNotifications();
        
        // SMS
        $this->sms_enabled = $settings->sms_enabled;
        $this->sms_provider = $settings->sms_provider ?? '';
        $this->sms_account_sid = $settings->sms_account_sid;
        $this->sms_auth_token = $settings->sms_auth_token;
        $this->sms_from_number = $settings->sms_from_number;
        $this->sms_api_token = $settings->sms_api_token;
        $this->sms_sender_id = $settings->sms_sender_id;
        $this->sms_notifications = $settings->sms_notifications ?? TenantNotificationSetting::getDefaultSmsNotifications();
        
        // WhatsApp
        $this->whatsapp_enabled = $settings->whatsapp_enabled;
        $this->whatsapp_provider = $settings->whatsapp_provider ?? 'twilio';
        $this->whatsapp_account_sid = $settings->whatsapp_account_sid;
        $this->whatsapp_auth_token = $settings->whatsapp_auth_token;
        $this->whatsapp_from_number = $settings->whatsapp_from_number;
        $this->whatsapp_business_account_id = $settings->whatsapp_business_account_id;
        $this->whatsapp_sandbox = $settings->whatsapp_sandbox;
        $this->whatsapp_notifications = $settings->whatsapp_notifications ?? TenantNotificationSetting::getDefaultWhatsAppNotifications();
        $this->whatsapp_templates = $settings->whatsapp_templates ?? [];
        $this->whatsapp_notification_templates = $settings->whatsapp_notification_templates ?? [];
        $this->sms_notification_templates = $settings->sms_notification_templates ?? [];
        $this->email_notification_templates = $settings->email_notification_templates ?? [];
        
        // Carregar templates do sistema (/notifications/templates)
        $this->loadNotificationTemplates($tenantId);
    }
    
    /**
     * Carregar templates configurados pelo usuário
     */
    protected function loadNotificationTemplates($tenantId)
    {
        $templates = \App\Models\NotificationTemplate::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
        
        $this->availableNotificationTemplates = $templates->map(function($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'module' => $template->module,
                'event' => $template->event,
                'whatsapp_enabled' => $template->whatsapp_enabled,
                'sms_enabled' => $template->sms_enabled,
                'email_enabled' => $template->email_enabled,
            ];
        })->toArray();
    }

    public function save()
    {
        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $settings = TenantNotificationSetting::getForTenant($tenantId);
        
        $settings->update([
            // Email
            'email_enabled' => $this->email_enabled,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => $this->smtp_username,
            'smtp_password' => $this->smtp_password,
            'smtp_encryption' => $this->smtp_encryption,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'email_notifications' => $this->email_notifications,
            // SMS
            'sms_enabled' => $this->sms_enabled,
            'sms_provider' => $this->sms_provider,
            'sms_account_sid' => $this->sms_account_sid,
            'sms_auth_token' => $this->sms_auth_token,
            'sms_from_number' => $this->sms_from_number,
            'sms_api_token' => $this->sms_api_token,
            'sms_sender_id' => $this->sms_sender_id,
            'sms_notifications' => $this->sms_notifications,
            'sms_notification_templates' => $this->sms_notification_templates,
            // WhatsApp
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'whatsapp_provider' => $this->whatsapp_provider,
            'whatsapp_account_sid' => $this->whatsapp_account_sid,
            'whatsapp_auth_token' => $this->whatsapp_auth_token,
            'whatsapp_from_number' => $this->whatsapp_from_number,
            'whatsapp_business_account_id' => $this->whatsapp_business_account_id,
            'whatsapp_sandbox' => $this->whatsapp_sandbox,
            'whatsapp_notifications' => $this->whatsapp_notifications,
            'whatsapp_templates' => $this->whatsapp_templates,
            'whatsapp_notification_templates' => $this->whatsapp_notification_templates,
            'email_notification_templates' => $this->email_notification_templates,
        ]);

        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => 'Configurações de notificações salvas com sucesso!'
        ]);
    }

    public function testEmailConnection()
    {
        $this->validate([
            'smtp_host' => 'required',
            'smtp_port' => 'required',
            'smtp_username' => 'required',
            'smtp_password' => 'required',
        ]);

        try {
            // Configure mailer temporarily
            config([
                'mail.mailers.smtp.host' => $this->smtp_host,
                'mail.mailers.smtp.port' => $this->smtp_port,
                'mail.mailers.smtp.username' => $this->smtp_username,
                'mail.mailers.smtp.password' => $this->smtp_password,
                'mail.mailers.smtp.encryption' => $this->smtp_encryption,
                'mail.from.address' => $this->from_email,
                'mail.from.name' => $this->from_name,
            ]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Configurações de email validadas com sucesso!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro ao testar email: ' . $e->getMessage()
            ]);
        }
    }

    public function testSmsConnection()
    {
        if ($this->sms_provider === 'd7networks') {
            $this->validate([
                'sms_api_token' => 'required',
            ]);

            try {
                $d7 = new \App\Services\D7NetworksService($this->sms_api_token, $this->sms_sender_id);
                $result = $d7->testConnection();

                if ($result['success']) {
                    $this->dispatch('show-toast', [
                        'type' => 'success',
                        'message' => $result['message'] . ' - Saldo: ' . $result['balance'] . ' ' . $result['currency']
                    ]);
                } else {
                    $this->dispatch('show-toast', [
                        'type' => 'error',
                        'message' => $result['message']
                    ]);
                }
            } catch (\Exception $e) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Erro ao testar D7 Networks: ' . $e->getMessage()
                ]);
            }
        } elseif ($this->sms_provider === 'twilio') {
            $this->validate([
                'sms_account_sid' => 'required',
                'sms_auth_token' => 'required',
            ]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Configurações do Twilio validadas!'
            ]);
        } else {
            $this->dispatch('show-toast', [
                'type' => 'info',
                'message' => 'Provider ' . $this->sms_provider . ' não suporta teste de conexão ainda.'
            ]);
        }
    }

    public function prepareTestWhatsApp()
    {
        $this->validate([
            'testPhone' => 'required|string',
            'testTemplateSid' => 'required|string',
            'whatsapp_account_sid' => 'required',
            'whatsapp_auth_token' => 'required',
            'whatsapp_from_number' => 'required',
        ], [
            'testTemplateSid.required' => 'Selecione um template para enviar o teste'
        ]);

        // Buscar template selecionado
        $this->selectedTemplate = collect(array_merge($this->whatsapp_templates, $this->availableWhatsAppTemplates))
            ->firstWhere('sid', $this->testTemplateSid);

        if (!$this->selectedTemplate) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Template não encontrado'
            ]);
            return;
        }

        // Verificar se template tem variáveis
        try {
            $whatsapp = new WhatsAppService(
                $this->whatsapp_account_sid,
                $this->whatsapp_auth_token,
                $this->whatsapp_from_number
            );
            
            $templateDetails = $whatsapp->getTemplateDetails($this->testTemplateSid);
            
            \Log::info('Template details fetched', ['details' => $templateDetails]);
            
            if ($templateDetails && isset($templateDetails['variables']) && count($templateDetails['variables']) > 0) {
                // Template tem variáveis - abrir modal
                $this->testTemplateVariables = [];
                foreach ($templateDetails['variables'] as $variable) {
                    $this->testTemplateVariables[$variable] = '';
                }
                $this->showVariablesModal = true;
                
                \Log::info('Opening variables modal', ['variables' => $this->testTemplateVariables]);
            } else {
                // Template sem variáveis - enviar direto
                \Log::info('No variables found, sending directly');
                $this->sendTestWhatsApp();
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching template details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Abrir modal com variáveis padrão do template
            $this->testTemplateVariables = [
                'date' => '',
                'var' => '',
                'event' => '',
                'number' => ''
            ];
            $this->showVariablesModal = true;
            
            $this->dispatch('show-toast', [
                'type' => 'warning',
                'message' => 'Não foi possível detectar variáveis automaticamente. Preencha as que forem necessárias.'
            ]);
        }
    }

    public function sendTestWhatsApp()
    {
        try {
            // Criar WhatsApp service com configurações do tenant
            $whatsapp = new WhatsAppService(
                $this->whatsapp_account_sid,
                $this->whatsapp_auth_token,
                $this->whatsapp_from_number
            );
            
            // Preparar variáveis no formato correto para Twilio
            $variables = [];
            foreach ($this->testTemplateVariables as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $variables[(string)$key] = (string)$value;
                }
            }
            
            \Log::info('Sending WhatsApp test', [
                'phone' => $this->testPhone,
                'template_sid' => $this->testTemplateSid,
                'template_name' => $this->selectedTemplate['name'] ?? 'test_template',
                'variables' => $variables
            ]);
            
            // Enviar usando template (obrigatório para WhatsApp Business)
            $result = $whatsapp->sendTemplate(
                $this->testPhone, 
                $this->selectedTemplate['name'] ?? 'test_template',
                $variables,
                $this->testTemplateSid
            );
            
            if ($result) {
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'Mensagem de teste WhatsApp enviada! SID: ' . $result
                ]);
                $this->testPhone = '';
                $this->testTemplateVariables = [];
                $this->showVariablesModal = false;
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Falha ao enviar mensagem de teste WhatsApp'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro: ' . $e->getMessage()
            ]);
        }
    }

    public function closeVariablesModal()
    {
        $this->showVariablesModal = false;
        $this->testTemplateVariables = [];
    }

    public function fetchWhatsAppTemplates()
    {
        $this->validate([
            'whatsapp_account_sid' => 'required',
            'whatsapp_auth_token' => 'required',
        ]);

        try {
            // Criar WhatsApp service com configurações do tenant
            $whatsapp = new WhatsAppService(
                $this->whatsapp_account_sid,
                $this->whatsapp_auth_token,
                $this->whatsapp_from_number
            );
            
            $this->availableWhatsAppTemplates = $whatsapp->fetchTemplates();
            
            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => count($this->availableWhatsAppTemplates) . ' templates encontrados!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro ao buscar templates: ' . $e->getMessage()
            ]);
        }
    }

    public function addWhatsAppTemplate($template)
    {
        if (!collect($this->whatsapp_templates)->contains('sid', $template['sid'])) {
            $this->whatsapp_templates[] = $template;
        }
    }

    public function removeWhatsAppTemplate($index)
    {
        unset($this->whatsapp_templates[$index]);
        $this->whatsapp_templates = array_values($this->whatsapp_templates);
    }

    public function render()
    {
        return view('livewire.settings.notification-settings');
    }
}
