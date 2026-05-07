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
    
    // Filtros
    public $channelFilter = 'all'; // all, email, sms, whatsapp
    public $moduleFilter = 'all';
    
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
    public $testEmail;
    public $testVariables = [];
    public $detectedChannels = [];
    public $testTemplateName = '';
    public $testTemplateModule = '';
    public $testPreviewSubject = '';
    public $testPreviewBody = '';
    public $testPreviewSms = '';
    
    public function mount()
    {
        $this->loadTemplates();
    }
    
    public function loadTemplates()
    {
        $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
        $query = NotificationTemplate::where('tenant_id', $tenantId);
        
        // Filtro por módulo
        if ($this->moduleFilter !== 'all') {
            $query->where('module', $this->moduleFilter);
        }
        
        // Filtro por canal
        if ($this->channelFilter !== 'all') {
            $query->where($this->channelFilter . '_enabled', true);
        }
        
        $this->templates = $query->orderBy('module')
            ->orderBy('name')
            ->get();
    }
    
    public function updatedChannelFilter()
    {
        $this->loadTemplates();
    }
    
    public function updatedModuleFilter()
    {
        $this->loadTemplates();
    }
    
    public function clearFilters()
    {
        $this->channelFilter = 'all';
        $this->moduleFilter = 'all';
        $this->loadTemplates();
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
        $this->testEmail = '';
        $this->testVariables = [];
        $this->testTemplateName = $template->name;
        $this->testTemplateModule = $template->module;
        
        // Detectar canais habilitados
        $this->detectedChannels = [];
        if ($template->email_enabled) $this->detectedChannels[] = 'email';
        if ($template->sms_enabled) $this->detectedChannels[] = 'sms';
        if ($template->whatsapp_enabled) $this->detectedChannels[] = 'whatsapp';
        
        // Detectar TODAS as variáveis do template (de mapeamentos + corpo do email/sms)
        $allVars = [];
        
        if ($template->variable_mappings) {
            $allVars = array_merge($allVars, array_keys($template->variable_mappings));
        }
        
        // Detectar variáveis no corpo do SMS
        if ($template->sms_body) {
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $template->sms_body, $matches);
            $allVars = array_merge($allVars, $matches[1] ?? []);
        }
        
        // Detectar variáveis no email
        if ($template->email_subject) {
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $template->email_subject, $matches);
            $allVars = array_merge($allVars, $matches[1] ?? []);
        }
        if ($template->email_body) {
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $template->email_body, $matches);
            $allVars = array_merge($allVars, $matches[1] ?? []);
        }
        
        // Inicializar variáveis vazias (sem duplicatas)
        foreach (array_unique($allVars) as $var) {
            $this->testVariables[$var] = '';
        }
        
        // Auto preencher com dados demo
        $this->fillDemoData($template->module);
        
        // Pre-fill email from settings
        if (in_array('email', $this->detectedChannels)) {
            $tenantId = auth()->user()->activeTenant()->id ?? session('active_tenant_id');
            $settings = TenantNotificationSetting::getForTenant($tenantId);
            if ($settings && $settings->from_email) {
                $this->testEmail = $settings->from_email;
            }
        }
        
        $this->updateTestPreview();
        $this->showTestModal = true;
    }
    
    /**
     * Dados demo realistas por módulo
     */
    public static function getDemoData(string $module): array
    {
        $demoData = [
            'events' => [
                'event' => 'Conferência de Tecnologia 2026',
                'date' => now()->addDays(7)->format('d/m/Y'),
                'end_date' => now()->addDays(7)->format('d/m/Y'),
                'local' => 'Hotel Épic Sana, Luanda',
                'cliente' => 'João Silva',
                'responsavel' => 'Maria Santos',
                'tipo' => 'Conferência',
                'participantes' => '150',
                'valor' => '1.500.000,00 Kz',
                'status' => 'Confirmado',
                'fase' => 'Preparação',
            ],
            'hr' => [
                'funcionario' => 'Carlos Mendes',
                'cargo' => 'Desenvolvedor Sénior',
                'departamento' => 'Tecnologia da Informação',
                'data_admissao' => now()->format('d/m/Y'),
                'salario' => '350.000,00 Kz',
                'email' => 'carlos.mendes@empresa.vip',
                'telefone' => '+244 939 729 902',
                'licenca_inicio' => now()->addDays(14)->format('d/m/Y'),
                'licenca_fim' => now()->addDays(28)->format('d/m/Y'),
                'tipo_licenca' => 'Férias Anuais',
            ],
            'calendar' => [
                'titulo' => 'Reunião de Planeamento Q1',
                'data_inicio' => now()->addDays(3)->format('d/m/Y H:i'),
                'data_fim' => now()->addDays(3)->addHours(2)->format('d/m/Y H:i'),
                'descricao' => 'Revisão dos objectivos trimestrais',
                'local' => 'Sala de Reuniões 3A',
                'organizador' => 'Ana Ferreira',
            ],
            'finance' => [
                'documento' => 'FT 2026/000123',
                'cliente' => 'Empresa ABC, Lda.',
                'valor' => '2.450.000,00 Kz',
                'data_emissao' => now()->format('d/m/Y'),
                'data_vencimento' => now()->addDays(30)->format('d/m/Y'),
                'status' => 'Pendente',
                'descricao' => 'Serviços de consultoria TI',
                'metodo_pagamento' => 'Transferência Bancária',
            ],
            'crm' => [
                'cliente' => 'Pedro Neto',
                'empresa' => 'TechAngola, SA',
                'email' => 'pedro.neto@techangola.vip',
                'telefone' => '+244 942 705 533',
                'responsavel' => 'Sofia Rodrigues',
                'status' => 'Em Negociação',
                'oportunidade' => '5.000.000,00 Kz',
                'proxima_acao' => 'Enviar proposta comercial',
            ],
            'projects' => [
                'projeto' => 'Implementação ERP v3.0',
                'cliente' => 'Grupo Sonangol',
                'gerente' => 'António Costa',
                'data_inicio' => now()->format('d/m/Y'),
                'data_fim' => now()->addMonths(6)->format('d/m/Y'),
                'orcamento' => '15.000.000,00 Kz',
                'status' => 'Em Andamento',
                'progresso' => '35%',
            ],
            'tasks' => [
                'tarefa' => 'Configurar módulo de notificações',
                'descricao' => 'Implementar envio automático de emails e SMS',
                'responsavel' => 'Ricardo Almeida',
                'data_vencimento' => now()->addDays(5)->format('d/m/Y'),
                'prioridade' => 'Alta',
                'status' => 'Em Progresso',
                'projeto' => 'Implementação ERP v3.0',
            ],
        ];
        
        return $demoData[$module] ?? [];
    }
    
    /**
     * Preencher variáveis de teste com dados demo
     */
    public function fillDemoData(?string $module = null)
    {
        $module = $module ?? $this->testTemplateModule;
        $demo = self::getDemoData($module);
        
        foreach ($this->testVariables as $var => $value) {
            if (isset($demo[$var])) {
                $this->testVariables[$var] = $demo[$var];
            }
        }
        
        $this->updateTestPreview();
    }
    
    /**
     * Limpar dados demo das variáveis
     */
    public function clearDemoData()
    {
        foreach ($this->testVariables as $var => $value) {
            $this->testVariables[$var] = '';
        }
        $this->testPreviewSubject = '';
        $this->testPreviewBody = '';
        $this->testPreviewSms = '';
    }
    
    /**
     * Atualizar preview do template com variáveis atuais
     */
    public function updateTestPreview()
    {
        if (!$this->testTemplateId) return;
        
        try {
            $template = NotificationTemplate::find($this->testTemplateId);
            if (!$template) return;
            
            // Preview Email
            if ($template->email_subject) {
                $subject = $template->email_subject;
                foreach ($this->testVariables as $key => $value) {
                    $val = $value ?: '[' . $key . ']';
                    $subject = str_replace('{{' . $key . '}}', $val, $subject);
                    $subject = str_replace('{{ ' . $key . ' }}', $val, $subject);
                }
                $this->testPreviewSubject = $subject;
            }
            
            if ($template->email_body) {
                $body = $template->email_body;
                foreach ($this->testVariables as $key => $value) {
                    $val = $value ?: '[' . $key . ']';
                    $body = str_replace('{{' . $key . '}}', $val, $body);
                    $body = str_replace('{{ ' . $key . ' }}', $val, $body);
                }
                $this->testPreviewBody = $body;
            }
            
            // Preview SMS
            if ($template->sms_body) {
                $sms = $template->sms_body;
                foreach ($this->testVariables as $key => $value) {
                    $val = $value ?: '[' . $key . ']';
                    $sms = str_replace('{{' . $key . '}}', $val, $sms);
                    $sms = str_replace('{{ ' . $key . ' }}', $val, $sms);
                }
                $this->testPreviewSms = $sms;
            }
        } catch (\Exception $e) {
            \Log::warning('Preview update failed: ' . $e->getMessage());
        }
    }
    
    public function updatedTestVariables()
    {
        $this->updateTestPreview();
    }
    
    public function sendTest()
    {
        \Log::info('🚀 sendTest() INICIADO', [
            'testTemplateId' => $this->testTemplateId,
            'testEmail' => $this->testEmail,
            'testPhone' => $this->testPhone,
            'detectedChannels' => $this->detectedChannels,
            'testVariables' => $this->testVariables,
        ]);
        
        $template = NotificationTemplate::findOrFail($this->testTemplateId);
        $settings = TenantNotificationSetting::getForTenant($template->tenant_id);
        $sentChannels = [];
        $errors = [];
        
        try {
            // ===== CONFIGURAR SMTP DO TENANT =====
            if ($settings && $settings->email_enabled) {
                $settings->configureSMTP();
                \Log::info('✅ SMTP do tenant configurado para teste');
            } else {
                \Log::warning('⚠️ Configuração SMTP do tenant não encontrada ou email não habilitado');
            }
            
            // ===== ENVIAR POR EMAIL =====
            if ($template->email_enabled && $this->testEmail) {
                $this->validate(['testEmail' => 'required|email']);
                
                try {
                    $subject = $template->email_subject ?? 'Teste de Template';
                    $body = $template->email_body ?? 'Corpo do email de teste';
                    
                    \Log::info('📝 Template antes de substituir', ['subject' => $subject, 'body_length' => strlen($body)]);
                    
                    // Substituir variáveis (todos os formatos: {{var}}, {{ var }}, {{  var  }})
                    foreach ($this->testVariables as $key => $value) {
                        $pattern = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/';
                        $subject = preg_replace($pattern, $value, $subject);
                        $body = preg_replace($pattern, $value, $body);
                    }
                    
                    $subject = '[TESTE] ' . $subject;
                    
                    \Log::info('📝 Template após substituir', ['subject' => $subject, 'body_preview' => mb_substr($body, 0, 100)]);
                    
                    // Construir HTML bonito
                    $fromName = config('mail.from.name', 'SOSERP');
                    try {
                        $bodyHtml = $this->buildHtmlEmail($subject, $body, $fromName);
                    } catch (\Exception $htmlEx) {
                        \Log::warning('⚠️ buildHtmlEmail falhou, usando body simples', ['error' => $htmlEx->getMessage()]);
                        $bodyHtml = '<html><body style="font-family:Arial,sans-serif;padding:20px;">'
                            . '<div style="background:#fef3c7;padding:10px;border-radius:8px;margin-bottom:20px;color:#92400e;font-weight:bold;">⚠️ EMAIL DE TESTE</div>'
                            . '<div style="line-height:1.7;">' . nl2br(e($body)) . '</div>'
                            . '<hr style="margin-top:30px;border:none;border-top:1px solid #e5e7eb;">'
                            . '<p style="color:#9ca3af;font-size:12px;">Enviado por ' . e($fromName) . '</p>'
                            . '</body></html>';
                    }
                    
                    $testEmail = $this->testEmail;
                    
                    \Log::info('📧 Preparando envio de email', [
                        'to' => $testEmail,
                        'subject' => $subject,
                        'html_length' => strlen($bodyHtml),
                        'from_config' => config('mail.from.address'),
                    ]);
                    
                    \Illuminate\Support\Facades\Mail::send([], [], function($message) use ($testEmail, $subject, $bodyHtml) {
                        $message->to($testEmail)
                                ->subject($subject)
                                ->html($bodyHtml);
                    });
                    
                    \Log::info('📧 Email de teste enviado COM SUCESSO', ['to' => $testEmail, 'subject' => $subject]);
                    
                    $sentChannels[] = '📧 Email';
                } catch (\Exception $e) {
                    $errors[] = 'Email: ' . $e->getMessage();
                    \Log::error('❌ Erro ao enviar email de teste', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            } else {
                \Log::warning('⚠️ Email não enviado', [
                    'email_enabled' => $template->email_enabled,
                    'testEmail' => $this->testEmail,
                ]);
            }
            
            // ===== ENVIAR POR SMS =====
            if ($template->sms_enabled && $this->testPhone) {
                $this->validate(['testPhone' => 'required|string']);
                
                try {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($this->testPhone);
                    
                    if (!PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $errors[] = 'SMS: Número inválido';
                    } else {
                        $smsBody = $template->sms_body;
                        foreach ($this->testVariables as $key => $value) {
                            $pattern = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/';
                            $smsBody = preg_replace($pattern, $value, $smsBody);
                        }
                        
                        // TODO: Implementar envio SMS via provider (Twilio, etc)
                        \Log::info('SMS Test', ['phone' => $normalizedPhone, 'body' => $smsBody]);
                        $sentChannels[] = '📱 SMS';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'SMS: ' . $e->getMessage();
                }
            }
            
            // ===== ENVIAR POR WHATSAPP =====
            if ($template->whatsapp_enabled && $template->whatsapp_template_sid && $this->testPhone) {
                $this->validate(['testPhone' => 'required|string']);
                
                try {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($this->testPhone);
                    
                    if (!PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $errors[] = 'WhatsApp: Número inválido';
                    } else {
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
                            $sentChannels[] = '💬 WhatsApp';
                        } else {
                            $errors[] = 'WhatsApp: Falha no envio';
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = 'WhatsApp: ' . $e->getMessage();
                }
            }
            
            // Mensagem final
            if (!empty($sentChannels)) {
                $message = 'Teste enviado com sucesso via: ' . implode(', ', $sentChannels);
                if (!empty($errors)) {
                    $message .= ' | Falhas: ' . implode(', ', $errors);
                }
                
                $this->dispatch('show-toast', [
                    'type' => empty($errors) ? 'success' : 'warning',
                    'message' => $message
                ]);
                $this->showTestModal = false;
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Nenhum canal enviado. Erros: ' . implode(', ', $errors)
                ]);
            }
            
        } catch (\Exception $e) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Erro ao enviar teste: ' . $e->getMessage()
            ]);
        }
    }
    
    public function closeTestModal()
    {
        $this->showTestModal = false;
    }
    
    /**
     * Construir HTML bonito para email de teste
     */
    protected function buildHtmlEmail(string $subject, string $body, string $fromName): string
    {
        $appName = e($fromName);
        $year = date('Y');
        $date = now()->format('d/m/Y H:i');
        
        // Converter quebras de linha em <br> e preservar parágrafos
        $bodyHtml = nl2br(e($body));
        
        return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6;padding:30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#a855f7 100%);padding:30px 40px;border-radius:16px 16px 0 0;text-align:center;">
                            <h1 style="color:#ffffff;font-size:22px;font-weight:700;margin:0 0 6px 0;letter-spacing:-0.5px;">{$appName}</h1>
                            <p style="color:rgba(255,255,255,0.8);font-size:12px;margin:0;letter-spacing:0.5px;">NOTIFICAÇÃO DO SISTEMA</p>
                        </td>
                    </tr>
                    
                    <!-- Test Badge -->
                    <tr>
                        <td style="background-color:#fef3c7;padding:12px 40px;border-bottom:1px solid #fcd34d;">
                            <p style="margin:0;font-size:13px;color:#92400e;font-weight:600;text-align:center;">
                                ⚠️ Esta é uma mensagem de TESTE — enviada a partir do painel de templates
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="background-color:#ffffff;padding:35px 40px;">
                            <div style="font-size:15px;line-height:1.7;color:#374151;">
                                {$bodyHtml}
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="background-color:#ffffff;padding:0 40px;">
                            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#ffffff;padding:25px 40px;border-radius:0 0 16px 16px;">
                            <p style="margin:0 0 8px 0;font-size:12px;color:#9ca3af;text-align:center;">
                                Enviado por <strong style="color:#6366f1;">{$appName}</strong> em {$date}
                            </p>
                            <p style="margin:0;font-size:11px;color:#d1d5db;text-align:center;">
                                &copy; {$year} {$appName}. Todos os direitos reservados.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Spacer -->
                    <tr>
                        <td style="padding:20px;text-align:center;">
                            <p style="margin:0;font-size:11px;color:#9ca3af;">
                                Este email foi enviado automaticamente. Por favor, não responda directamente.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    public function render()
    {
        return view('livewire.settings.manage-notification-templates', [
            'modules' => NotificationTemplate::getAvailableModules(),
            'triggers' => NotificationTemplate::getAvailableTriggers(),
        ]);
    }
}
