<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\WhatsAppService;
use App\Helpers\PhoneHelper;

#[Layout('layouts.app')]
class ManageNotificationTemplates extends Component
{
    public $templates;
    public $showModal = false;
    public $showVariableModal = false;
    public $editing = false;
    
    // Form fields
    public $templateId;
    public $name;
    public $slug;
    public $module = 'events';
    public $description;
    
    // Canais
    public $email_enabled = false;
    public $sms_enabled = false;
    public $whatsapp_enabled = false;
    
    // Templates
    public $email_template_id;
    public $email_subject;
    public $email_body;
    public $sms_template_sid;
    public $sms_body;
    public $whatsapp_template_sid;
    
    // Timing
    public $trigger_event = 'created';
    public $notify_before_minutes;
    public $notify_at_time;
    
    // Variáveis
    public $variable_mappings = [];
    public $conditions = [];
    public $is_active = true;
    
    // Disponíveis
    public $availableWhatsAppTemplates = [];
    public $availableFields = [];
    public $detectedVariables = [];
    public $moduleVariables = [];
    
    // Test
    public $showTestModal = false;
    public $testTemplateId;
    public $testPhone;
    public $testVariables = [];
    
    public function mount()
    {
        $this->loadTemplates();
    }
    
    public function loadTemplates()
    {
        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $this->templates = NotificationTemplate::where('tenant_id', $tenantId)
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }
    
    public function create()
    {
        $this->reset(['templateId', 'name', 'slug', 'description', 'variable_mappings', 'conditions']);
        $this->editing = false;
        $this->showModal = true;
        $this->updateAvailableFields();
        $this->loadModuleVariables();
        
        // Templates do WhatsApp só são carregados ao clicar no botão "Carregar"
    }
    
    public function updatedModule()
    {
        $this->loadModuleVariables();
        $this->updateAvailableFields();
    }
    
    protected function loadModuleVariables()
    {
        $this->moduleVariables = NotificationTemplate::getModuleVariables($this->module);
    }
    
    public function updatedWhatsappEnabled()
    {
        // Templates do WhatsApp só são carregados ao clicar no botão "Carregar"
    }
    
    public function updatedSmsEnabled()
    {
        // Templates do WhatsApp só são carregados ao clicar no botão "Carregar"
    }
    
    public function edit($id)
    {
        $template = NotificationTemplate::findOrFail($id);
        
        $this->templateId = $template->id;
        $this->name = $template->name;
        $this->slug = $template->slug;
        $this->module = $template->module;
        $this->description = $template->description;
        
        $this->email_enabled = $template->email_enabled;
        $this->sms_enabled = $template->sms_enabled;
        $this->whatsapp_enabled = $template->whatsapp_enabled;
        
        $this->email_subject = $template->email_subject;
        $this->email_body = $template->email_body;
        $this->sms_body = $template->sms_body;
        $this->email_template_id = $template->email_template_id;
        $this->sms_template_sid = $template->sms_template_sid;
        $this->whatsapp_template_sid = $template->whatsapp_template_sid;
        
        $this->trigger_event = $template->trigger_event;
        $this->notify_before_minutes = $template->notify_before_minutes;
        $this->notify_at_time = $template->notify_at_time;
        
        $this->variable_mappings = $template->variable_mappings ?? [];
        $this->conditions = $template->conditions ?? [];
        $this->is_active = $template->is_active;
        
        $this->editing = true;
        $this->showModal = true;
        $this->updateAvailableFields();
        $this->loadModuleVariables();
        
        // Templates do WhatsApp só são carregados ao clicar no botão "Carregar"
        if ($this->whatsapp_template_sid) {
            $this->loadTemplateVariables();
        }
    }
    
    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'module' => 'required|string',
            'trigger_event' => 'required|string',
        ]);
        
        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        
        // Auto-mapear variáveis do SMS e Email
        $this->autoMapVariables();
        
        $data = [
            'tenant_id' => $tenantId,
            'name' => $this->name,
            'slug' => $this->slug ?: \Str::slug($this->name),
            'module' => $this->module,
            'description' => $this->description,
            'email_enabled' => $this->email_enabled,
            'sms_enabled' => $this->sms_enabled,
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'email_subject' => $this->email_subject,
            'email_body' => $this->email_body,
            'sms_body' => $this->sms_body,
            'email_template_id' => $this->email_template_id,
            'sms_template_sid' => $this->sms_template_sid,
            'whatsapp_template_sid' => $this->whatsapp_template_sid,
            'trigger_event' => $this->trigger_event,
            'notify_before_minutes' => $this->notify_before_minutes,
            'notify_at_time' => $this->notify_at_time,
            'variable_mappings' => $this->variable_mappings,
            'conditions' => $this->conditions,
            'is_active' => $this->is_active,
        ];
        
        if ($this->editing) {
            NotificationTemplate::findOrFail($this->templateId)->update($data);
            $message = 'Template atualizado com sucesso!';
        } else {
            NotificationTemplate::create($data);
            $message = 'Template criado com sucesso!';
        }
        
        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => $message
        ]);
        
        $this->showModal = false;
        $this->loadTemplates();
    }
    
    /**
     * Auto-mapear variáveis encontradas no SMS e Email
     */
    protected function autoMapVariables()
    {
        // Obter variáveis disponíveis do módulo
        $moduleVariables = NotificationTemplate::getModuleVariables($this->module);
        
        // Se já tem mapeamento do WhatsApp, não sobrescrever
        if (!empty($this->variable_mappings)) {
            return;
        }
        
        $detectedVariables = [];
        
        // Detectar variáveis no corpo do SMS
        if ($this->sms_enabled && $this->sms_body) {
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->sms_body, $matches);
            $detectedVariables = array_merge($detectedVariables, $matches[1]);
        }
        
        // Detectar variáveis no email
        if ($this->email_enabled) {
            if ($this->email_subject) {
                preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->email_subject, $matches);
                $detectedVariables = array_merge($detectedVariables, $matches[1]);
            }
            if ($this->email_body) {
                preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->email_body, $matches);
                $detectedVariables = array_merge($detectedVariables, $matches[1]);
            }
        }
        
        // Criar mapeamento automático
        $mappings = [];
        foreach (array_unique($detectedVariables) as $varName) {
            if (isset($moduleVariables[$varName])) {
                $mappings[$varName] = $moduleVariables[$varName]['field'];
            }
        }
        
        if (!empty($mappings)) {
            $this->variable_mappings = $mappings;
        }
    }
    
    public function delete($id)
    {
        NotificationTemplate::findOrFail($id)->delete();
        
        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => 'Template excluído com sucesso!'
        ]);
        
        $this->loadTemplates();
    }
    
    public function toggleActive($id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->update(['is_active' => !$template->is_active]);
        
        $this->loadTemplates();
        
        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => 'Status atualizado!'
        ]);
    }
    
    public function loadAvailableTemplates()
    {
        try {
            $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
            $settings = TenantNotificationSetting::getForTenant($tenantId);
            
            if ($settings->whatsapp_account_sid && $settings->whatsapp_auth_token) {
                $whatsapp = new WhatsAppService(
                    $settings->whatsapp_account_sid,
                    $settings->whatsapp_auth_token,
                    $settings->whatsapp_from_number
                );
                
                $this->availableWhatsAppTemplates = $whatsapp->fetchTemplates();
                
                \Log::info('Templates loaded', [
                    'count' => count($this->availableWhatsAppTemplates),
                    'templates' => $this->availableWhatsAppTemplates
                ]);
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'warning',
                    'message' => 'Configure as credenciais do WhatsApp primeiro em Configurações'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro ao carregar templates: ' . $e->getMessage()
            ]);
            
            \Log::error('Error loading templates', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function updatedWhatsappTemplateSid()
    {
        $this->loadTemplateVariables();
    }
    
    public function loadTemplateVariables()
    {
        if (!$this->whatsapp_template_sid) return;
        
        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $settings = TenantNotificationSetting::getForTenant($tenantId);
        
        $whatsapp = new WhatsAppService(
            $settings->whatsapp_account_sid,
            $settings->whatsapp_auth_token,
            $settings->whatsapp_from_number
        );
        
        $details = $whatsapp->getTemplateDetails($this->whatsapp_template_sid);
        
        if ($details && isset($details['variables'])) {
            $this->detectedVariables = $details['variables'];
            
            // Inicializar mapeamentos vazios para novas variáveis
            foreach ($this->detectedVariables as $var) {
                if (!isset($this->variable_mappings[$var])) {
                    $this->variable_mappings[$var] = '';
                }
            }
        }
    }
    
    public function updateAvailableFields()
    {
        // Campos disponíveis por módulo
        $fields = [
            'events' => [
                'id' => 'ID',
                'name' => 'Nome do Evento',
                'description' => 'Descrição',
                'start_date' => 'Data de Início',
                'end_date' => 'Data de Fim',
                'start_time' => 'Hora de Início',
                'location' => 'Local',
                'organizer.name' => 'Organizador',
                'created_at' => 'Data de Criação',
            ],
            'hr' => [
                'id' => 'ID',
                'first_name' => 'Nome',
                'last_name' => 'Sobrenome',
                'email' => 'Email',
                'phone' => 'Telefone',
                'job_title' => 'Cargo',
                'department.name' => 'Departamento',
                'start_date' => 'Data de Início',
                'salary' => 'Salário',
            ],
            'finance' => [
                'id' => 'ID',
                'invoice_number' => 'Número da Fatura',
                'amount' => 'Valor',
                'due_date' => 'Data de Vencimento',
                'status' => 'Status',
                'client.name' => 'Cliente',
            ],
            'calendar' => [
                'id' => 'ID',
                'title' => 'Título',
                'start_datetime' => 'Data/Hora Início',
                'end_datetime' => 'Data/Hora Fim',
                'location' => 'Local',
                'attendees_count' => 'Número de Participantes',
            ],
        ];
        
        $this->availableFields = $fields[$this->module] ?? [];
    }
    
    public function addCondition()
    {
        $this->conditions[] = [
            'field' => '',
            'operator' => '=',
            'value' => ''
        ];
    }
    
    public function removeCondition($index)
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }
    
    public function closeModal()
    {
        $this->showModal = false;
    }
    
    public function openTestModal($id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $this->testTemplateId = $id;
        $this->testPhone = '';
        $this->testVariables = [];
        
        // Inicializar variáveis do template
        if ($template->variable_mappings) {
            foreach (array_keys($template->variable_mappings) as $var) {
                $this->testVariables[$var] = '';
            }
        }
        
        $this->showTestModal = true;
    }
    
    public function sendTest()
    {
        $this->validate([
            'testPhone' => 'required|string',
        ]);
        
        try {
            // Normalizar número angolano
            $normalizedPhone = PhoneHelper::normalizeAngolanPhone($this->testPhone);
            
            if (!PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Número de telefone inválido! Use formato: 939729902 ou +244939729902'
                ]);
                return;
            }
            
            $template = NotificationTemplate::findOrFail($this->testTemplateId);
            $settings = TenantNotificationSetting::getForTenant($template->tenant_id);
            
            if ($template->whatsapp_enabled && $template->whatsapp_template_sid) {
                $whatsapp = new WhatsAppService(
                    $settings->whatsapp_account_sid,
                    $settings->whatsapp_auth_token,
                    $settings->whatsapp_from_number
                );
                
                $result = $whatsapp->sendTemplate(
                    $normalizedPhone,
                    $template->name,
                    $this->testVariables,
                    $template->whatsapp_template_sid
                );
                
                if ($result) {
                    $this->dispatch('show-toast', [
                        'type' => 'success',
                        'message' => 'Teste enviado com sucesso para ' . PhoneHelper::formatAngolanPhone($normalizedPhone) . '! SID: ' . $result
                    ]);
                    $this->showTestModal = false;
                } else {
                    $this->dispatch('show-toast', [
                        'type' => 'error',
                        'message' => 'Falha ao enviar teste'
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro: ' . $e->getMessage()
            ]);
        }
    }
    
    public function closeTestModal()
    {
        $this->showTestModal = false;
    }
    
    public function render()
    {
        return view('livewire.settings.manage-notification-templates', [
            'modules' => NotificationTemplate::getAvailableModules(),
            'triggers' => NotificationTemplate::getAvailableTriggers(),
        ]);
    }
}
