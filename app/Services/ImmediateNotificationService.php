<?php

namespace App\Services;

use App\Models\TenantNotificationSetting;
use App\Models\NotificationTemplate;
use App\Helpers\PhoneHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ImmediateNotificationService
{
    protected $whatsappService;
    protected $smsService;
    protected $settings;
    protected $tenantId;
    
    public function __construct($tenantId = null)
    {
        // Aceitar tenant_id direto ou buscar do usuário autenticado
        $this->tenantId = $tenantId ?? auth()->user()?->activeTenant()?->id ?? session('active_tenant_id');
        
        if ($this->tenantId) {
            $this->settings = TenantNotificationSetting::getForTenant($this->tenantId);
            
            if ($this->settings->whatsapp_enabled) {
                $this->whatsappService = new WhatsAppService(
                    $this->settings->whatsapp_account_sid,
                    $this->settings->whatsapp_auth_token,
                    $this->settings->whatsapp_from_number
                );
            }
            
            // Inicializar serviço SMS baseado no provedor
            if ($this->settings->sms_enabled) {
                $this->initializeSmsService();
            }
        }
    }
    
    /**
     * Inicializa serviço SMS baseado no provedor configurado
     */
    protected function initializeSmsService()
    {
        $provider = $this->settings->sms_provider ?? 'twilio';
        
        if ($provider === 'd7networks') {
            // Criar serviço D7 Networks
            $this->smsService = new \App\Services\D7SmsService(
                $this->settings->sms_api_token,
                $this->settings->sms_sender_id
            );
        } else {
            // Usar Twilio para SMS
            if ($this->settings->sms_account_sid && $this->settings->sms_auth_token) {
                $this->smsService = new WhatsAppService(
                    $this->settings->sms_account_sid,
                    $this->settings->sms_auth_token,
                    $this->settings->sms_from_number
                );
            }
        }
    }
    
    /**
     * Envia notificação quando evento é criado
     */
    public function notifyEventCreated($event, $technicians = [])
    {
        if (!$this->isEnabled('event_created')) {
            return;
        }
        
        // Buscar templates configurados para cada canal
        $templates = $this->getConfiguredTemplate('event_created');
        
        if (empty($templates)) {
            Log::warning('No templates configured for event_created');
            return;
        }
        
        // O template já tem o mapeamento configurado!
        // Passar o evento inteiro e deixar o template mapear as variáveis
        $this->sendToTechniciansWithTemplates($technicians, $templates, $event, 'event_created');
        
        Log::info('Event Created Notification sent', [
            'event_id' => $event->id,
            'technicians_count' => count($technicians),
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando técnico é designado
     */
    public function notifyTechnicianAssigned($event, $technician)
    {
        if (!$this->isEnabled('technician_assigned')) {
            return;
        }
        
        // Buscar templates configurados
        $templates = $this->getConfiguredTemplate('technician_assigned');
        
        if (empty($templates)) {
            Log::warning('No templates configured for technician_assigned');
            return;
        }
        
        // O template já tem o mapeamento configurado!
        $this->sendToUserWithTemplates($technician, $templates, $event, 'technician_assigned');
        
        Log::info('Technician Assigned Notification sent', [
            'event_id' => $event->id,
            'technician_id' => $technician->id,
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando evento é cancelado
     */
    public function notifyEventCancelled($event, $technicians = [])
    {
        if (!$this->isEnabled('event_cancelled')) {
            return;
        }
        
        // Buscar templates configurados
        $templates = $this->getConfiguredTemplate('event_cancelled');
        
        if (empty($templates)) {
            Log::warning('No templates configured for event_cancelled');
            return;
        }
        
        // O template já tem o mapeamento configurado!
        $this->sendToTechniciansWithTemplates($technicians, $templates, $event, 'event_cancelled');
        
        Log::info('Event Cancelled Notification sent', [
            'event_id' => $event->id,
            'technicians_count' => count($technicians),
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando tarefa é atribuída
     */
    public function notifyTaskAssigned($task, $assignedUser)
    {
        if (!$this->isEnabled('task_assigned')) {
            return;
        }
        
        $message = "📋 Nova Tarefa Atribuída!\n\n" .
                   "📝 Tarefa: {$task->title}\n" .
                   "📅 Prazo: " . date('d/m/Y', strtotime($task->due_date)) . "\n" .
                   "🎯 Prioridade: {$task->priority}\n\n" .
                   "Acesse o sistema para mais detalhes.";
        
        $this->sendToUser($assignedUser, $message, 'task_assigned');
        
        Log::info('Task Assigned Notification sent', [
            'task_id' => $task->id,
            'user_id' => $assignedUser->id
        ]);
    }
    
    /**
     * Envia notificação quando reunião é agendada
     */
    public function notifyMeetingScheduled($meeting, $participants = [])
    {
        if (!$this->isEnabled('meeting_scheduled')) {
            return;
        }
        
        $message = "🤝 Reunião Agendada!\n\n" .
                   "📋 Assunto: {$meeting->subject}\n" .
                   "📍 Local: {$meeting->location}\n" .
                   "🗓️ Data: " . date('d/m/Y H:i', strtotime($meeting->scheduled_at)) . "\n" .
                   "⏰ Duração: {$meeting->duration} minutos\n\n" .
                   "Confirme sua presença no sistema.";
        
        $this->sendToMultipleUsers($participants, $message, 'meeting_scheduled');
        
        Log::info('Meeting Scheduled Notification sent', [
            'meeting_id' => $meeting->id,
            'participants_count' => count($participants)
        ]);
    }
    
    /**
     * Envia notificação quando funcionário é criado
     * Usa a mesma lógica do notifyEventCreated
     */
    public function notifyEmployeeCreated($employee)
    {
        if (!$this->isEnabled('employee_created')) {
            return;
        }
        
        // Buscar templates configurados para cada canal
        $templates = $this->getConfiguredTemplate('employee_created');
        
        if (empty($templates)) {
            Log::warning('No templates configured for employee_created');
            return;
        }
        
        // Se tem User, enviar via User (mesma lógica de eventos)
        if ($employee->user) {
            $this->sendToUserWithTemplates($employee->user, $templates, $employee, 'employee_created');
        } 
        // Se não tem User, enviar direto para email/telefone do Employee
        // Aplicar as mesmas validações do sendToUserWithTemplates
        else {
            // WhatsApp
            if (isset($templates['whatsapp']) && $this->settings->whatsapp_enabled && $this->isChannelEnabled('whatsapp', 'employee_created')) {
                $template = $templates['whatsapp'];
                if ($template->whatsapp_enabled && $employee->phone) {
                    $phone = PhoneHelper::normalizeAngolanPhone($employee->phone);
                    if (PhoneHelper::isValidAngolanPhone($phone)) {
                        $this->sendWhatsAppWithTemplate($phone, $template, $employee);
                    }
                }
            }
            
            // SMS
            if (isset($templates['sms']) && $this->settings->sms_enabled && $this->isChannelEnabled('sms', 'employee_created')) {
                $template = $templates['sms'];
                if ($template->sms_enabled && $employee->phone) {
                    $phone = PhoneHelper::normalizeAngolanPhone($employee->phone);
                    if (PhoneHelper::isValidAngolanPhone($phone)) {
                        $this->sendSMSWithTemplate($phone, $template, $employee);
                    }
                }
            }
            
            // Email
            if (isset($templates['email']) && $this->settings->email_enabled && $this->isChannelEnabled('email', 'employee_created')) {
                $template = $templates['email'];
                if ($template->email_enabled && $employee->email) {
                    if (filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
                        $this->sendEmailWithTemplate($employee->email, $template, $employee);
                    }
                }
            }
        }
        
        Log::info('Employee Created Notification sent', [
            'employee_id' => $employee->id,
            'has_user' => $employee->user ? 'yes' : 'no',
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando adiantamento é aprovado
     */
    public function notifyAdvanceApproved($advance)
    {
        if (!$this->isEnabled('advance_approved')) {
            return;
        }
        
        $templates = $this->getConfiguredTemplate('advance_approved');
        
        if (empty($templates)) {
            Log::warning('No templates configured for advance_approved');
            return;
        }
        
        // Enviar para o funcionário
        if ($advance->employee && $advance->employee->user) {
            $this->sendToUserWithTemplates($advance->employee->user, $templates, $advance, 'advance_approved');
        }
        
        Log::info('Advance Approved Notification sent', [
            'advance_id' => $advance->id,
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando adiantamento é rejeitado
     */
    public function notifyAdvanceRejected($advance)
    {
        if (!$this->isEnabled('advance_rejected')) {
            return;
        }
        
        $templates = $this->getConfiguredTemplate('advance_rejected');
        
        if (empty($templates)) {
            Log::warning('No templates configured for advance_rejected');
            return;
        }
        
        // Enviar para o funcionário
        if ($advance->employee && $advance->employee->user) {
            $this->sendToUserWithTemplates($advance->employee->user, $templates, $advance, 'advance_rejected');
        }
        
        Log::info('Advance Rejected Notification sent', [
            'advance_id' => $advance->id,
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando férias são aprovadas
     */
    public function notifyLeaveApproved($leave)
    {
        if (!$this->isEnabled('leave_approved')) {
            return;
        }
        
        $templates = $this->getConfiguredTemplate('leave_approved');
        
        if (empty($templates)) {
            Log::warning('No templates configured for leave_approved');
            return;
        }
        
        // Enviar para o funcionário
        if ($leave->employee && $leave->employee->user) {
            $this->sendToUserWithTemplates($leave->employee->user, $templates, $leave, 'leave_approved');
        }
        
        Log::info('Leave Approved Notification sent', [
            'leave_id' => $leave->id,
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando férias são rejeitadas
     */
    public function notifyLeaveRejected($leave)
    {
        if (!$this->isEnabled('leave_rejected')) {
            return;
        }
        
        $templates = $this->getConfiguredTemplate('leave_rejected');
        
        if (empty($templates)) {
            Log::warning('No templates configured for leave_rejected');
            return;
        }
        
        // Enviar para o funcionário
        if ($leave->employee && $leave->employee->user) {
            $this->sendToUserWithTemplates($leave->employee->user, $templates, $leave, 'leave_rejected');
        }
        
        Log::info('Leave Rejected Notification sent', [
            'leave_id' => $leave->id,
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia notificação quando recibo de pagamento está pronto
     */
    public function notifyPayslipReady($payslip)
    {
        if (!$this->isEnabled('payslip_ready')) {
            return;
        }
        
        $templates = $this->getConfiguredTemplate('payslip_ready');
        
        if (empty($templates)) {
            Log::warning('No templates configured for payslip_ready');
            return;
        }
        
        // Enviar para o funcionário
        if ($payslip->employee && $payslip->employee->user) {
            $this->sendToUserWithTemplates($payslip->employee->user, $templates, $payslip, 'payslip_ready');
        }
        
        Log::info('Payslip Ready Notification sent', [
            'payslip_id' => $payslip->id,
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia lembrete de evento
     */
    public function notifyEventReminder($event, $technicians = [])
    {
        if (!$this->isEnabled('event_reminder')) {
            return;
        }
        
        $templates = $this->getConfiguredTemplate('event_reminder');
        
        if (empty($templates)) {
            Log::warning('No templates configured for event_reminder');
            return;
        }
        
        $this->sendToTechniciansWithTemplates($technicians, $templates, $event, 'event_reminder');
        
        Log::info('Event Reminder Notification sent', [
            'event_id' => $event->id,
            'technicians_count' => count($technicians),
            'templates' => array_keys($templates)
        ]);
    }
    
    /**
     * Envia para múltiplos técnicos
     */
    protected function sendToTechnicians(array $technicians, string $message, string $notificationType)
    {
        foreach ($technicians as $technician) {
            $this->sendToUser($technician, $message, $notificationType);
        }
    }
    
    /**
     * Envia para múltiplos usuários
     */
    protected function sendToMultipleUsers(array $users, string $message, string $notificationType)
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $message, $notificationType);
        }
    }
    
    /**
     * Envia notificação para um usuário
     */
    protected function sendToUser($user, string $message, string $notificationType)
    {
        // WhatsApp
        if ($this->settings->whatsapp_enabled && $this->isChannelEnabled('whatsapp', $notificationType)) {
            $phone = PhoneHelper::normalizeAngolanPhone($user->phone);
            if (PhoneHelper::isValidAngolanPhone($phone)) {
                $this->sendWhatsApp($phone, $message);
            }
        }
        
        // SMS
        if ($this->settings->sms_enabled && $this->isChannelEnabled('sms', $notificationType)) {
            $phone = PhoneHelper::normalizeAngolanPhone($user->phone);
            if (PhoneHelper::isValidAngolanPhone($phone)) {
                $this->sendSMS($phone, $message);
            }
        }
        
        // Email
        if ($this->settings->email_enabled && $this->isChannelEnabled('email', $notificationType)) {
            if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $this->sendEmail($user->email, $message, $notificationType);
            }
        }
    }
    
    /**
     * Envia WhatsApp
     */
    protected function sendWhatsApp(string $phone, string $message)
    {
        try {
            if ($this->whatsappService) {
                // Usar template ou mensagem simples
                $result = $this->whatsappService->sendMessage($phone, $message);
                
                Log::info('WhatsApp sent', [
                    'phone' => $phone,
                    'result' => $result
                ]);
                
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }
    
    /**
     * Envia SMS usando o provedor configurado (D7 ou Twilio)
     */
    protected function sendSMS(string $phone, string $message)
    {
        try {
            if ($this->smsService) {
                // Enviar SMS via provedor configurado
                $result = $this->smsService->sendSMS($phone, $message);
                
                Log::info('SMS sent via ' . get_class($this->smsService), [
                    'phone' => $phone,
                    'message' => substr($message, 0, 100),
                    'result' => $result
                ]);
                
                return $result;
            } else {
                Log::warning('SMS service not configured');
                return false;
            }
        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }
    
    /**
     * Envia Email
     */
    protected function sendEmail(string $email, string $message, string $subject)
    {
        try {
            // Implementar envio de email
            Log::info('Email would be sent', [
                'email' => $email,
                'subject' => $subject
            ]);
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Verifica se o tipo de notificação está habilitado
     */
    protected function isEnabled(string $notificationType): bool
    {
        if (!$this->settings) {
            return false;
        }
        
        $whatsapp = $this->settings->whatsapp_notifications[$notificationType] ?? false;
        $sms = $this->settings->sms_notifications[$notificationType] ?? false;
        $email = $this->settings->email_notifications[$notificationType] ?? false;
        
        return $whatsapp || $sms || $email;
    }
    
    /**
     * Verifica se canal específico está habilitado para o tipo
     */
    protected function isChannelEnabled(string $channel, string $notificationType): bool
    {
        if (!$this->settings) {
            return false;
        }
        
        $channelKey = $channel . '_notifications';
        return $this->settings->$channelKey[$notificationType] ?? false;
    }
    
    /**
     * Busca template configurado para um tipo de notificação
     * Retorna array com templates para cada canal
     */
    protected function getConfiguredTemplate(string $notificationType): array
    {
        if (!$this->settings) {
            return [];
        }
        
        $templates = [];
        
        // WhatsApp
        $whatsappTemplateId = $this->settings->whatsapp_notification_templates[$notificationType] ?? null;
        if ($whatsappTemplateId) {
            $templates['whatsapp'] = NotificationTemplate::find($whatsappTemplateId);
        }
        
        // SMS
        $smsTemplateId = $this->settings->sms_notification_templates[$notificationType] ?? null;
        if ($smsTemplateId) {
            $templates['sms'] = NotificationTemplate::find($smsTemplateId);
        }
        
        // Email
        $emailTemplateId = $this->settings->email_notification_templates[$notificationType] ?? null;
        if ($emailTemplateId) {
            $templates['email'] = NotificationTemplate::find($emailTemplateId);
        }
        
        return $templates;
    }
    
    /**
     * Envia notificação para técnicos usando templates configurados (múltiplos canais)
     */
    protected function sendToTechniciansWithTemplates(array $technicians, array $templates, $record, string $notificationType)
    {
        foreach ($technicians as $technician) {
            $this->sendToUserWithTemplates($technician, $templates, $record, $notificationType);
        }
    }
    
    /**
     * Envia notificação para usuário usando templates configurados (múltiplos canais)
     */
    protected function sendToUserWithTemplates($user, array $templates, $record, string $notificationType)
    {
        // WhatsApp
        if (isset($templates['whatsapp']) && $this->settings->whatsapp_enabled && $this->isChannelEnabled('whatsapp', $notificationType)) {
            $template = $templates['whatsapp'];
            if ($template->whatsapp_enabled) {
                $phone = PhoneHelper::normalizeAngolanPhone($user->phone);
                if (PhoneHelper::isValidAngolanPhone($phone)) {
                    $this->sendWhatsAppWithTemplate($phone, $template, $record);
                }
            }
        }
        
        // SMS
        if (isset($templates['sms']) && $this->settings->sms_enabled && $this->isChannelEnabled('sms', $notificationType)) {
            $template = $templates['sms'];
            if ($template->sms_enabled) {
                $phone = PhoneHelper::normalizeAngolanPhone($user->phone);
                if (PhoneHelper::isValidAngolanPhone($phone)) {
                    $this->sendSMSWithTemplate($phone, $template, $record);
                }
            }
        }
        
        // Email
        if (isset($templates['email']) && $this->settings->email_enabled && $this->isChannelEnabled('email', $notificationType)) {
            $template = $templates['email'];
            if ($template->email_enabled) {
                if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    $this->sendEmailWithTemplate($user->email, $template, $record);
                }
            }
        }
    }
    
    /**
     * Envia WhatsApp usando template configurado
     * O template já tem o mapeamento de variáveis - basta passar o record
     */
    protected function sendWhatsAppWithTemplate(string $phone, NotificationTemplate $template, $record)
    {
        try {
            if ($this->whatsappService && $template->whatsapp_template_sid) {
                // O template faz o mapeamento automático usando a configuração salva!
                $mappedVariables = $template->mapVariables($record);
                
                Log::info('Template mapping', [
                    'template_id' => $template->id,
                    'record_type' => get_class($record),
                    'mapped_variables' => $mappedVariables
                ]);
                
                // Enviar usando template do Twilio
                $result = $this->whatsappService->sendTemplate(
                    $phone,
                    $template->name,
                    $mappedVariables,
                    $template->whatsapp_template_sid
                );
                
                Log::info('WhatsApp sent with template', [
                    'phone' => $phone,
                    'template_id' => $template->id,
                    'template_sid' => $template->whatsapp_template_sid,
                    'result' => $result
                ]);
                
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp template send failed', [
                'phone' => $phone,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return false;
    }
    
    /**
     * Envia SMS usando template configurado com corpo personalizado
     */
    protected function sendSMSWithTemplate(string $phone, NotificationTemplate $template, $record)
    {
        try {
            if (!$template->sms_body) {
                Log::warning('Template SMS sem corpo de mensagem', [
                    'template_id' => $template->id
                ]);
                return false;
            }
            
            // Processar corpo do SMS com variáveis
            $message = $template->getSmsBody($record);
            
            // Enviar SMS real via Twilio
            if ($this->whatsappService) {
                $result = $this->sendSMS($phone, $message);
                
                Log::info('SMS sent with template', [
                    'phone' => $phone,
                    'template_id' => $template->id,
                    'message' => $message,
                    'result' => $result
                ]);
                
                return $result;
            }
            
            Log::info('SMS template ready (no service configured)', [
                'phone' => $phone,
                'template_id' => $template->id,
                'message' => $message
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('SMS template send failed', [
                'phone' => $phone,
                'template_id' => $template->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }
    
    /**
     * Envia Email usando template configurado
     * MESMA LÓGICA EXATA DO RegisterWizard->sendWelcomeEmail()
     * 
     * 1. template->renderEmail($record) - Cria arquivo temporário Blade
     * 2. Renderiza via view()->render() - Processa @extends, @if, etc
     * 3. Mail::send([], [], callback)->html() - Envia HTML completo
     * 
     * ✅ Arquivo temporário Blade (renderiza @extends)
     * ✅ HTML completo com DOCTYPE já renderizado
     * ✅ CSS inline aplicado
     * ✅ Gradiente azul-roxo, logo, footer
     * ✅ NÃO CAI NO SPAM
     */
    protected function sendEmailWithTemplate(string $email, NotificationTemplate $template, $record)
    {
        try {
            if (!$template->email_body) {
                Log::warning('Template Email sem corpo de mensagem', [
                    'template_id' => $template->id
                ]);
                return false;
            }
            
            // Configurar SMTP dinamicamente usando as configurações do tenant
            if ($this->settings->smtp_host) {
                $this->configureSMTP();
            }
            
            // Renderizar template do BD (MESMA LÓGICA DO RegisterWizard)
            // Cria arquivo temporário, renderiza @extends, deleta arquivo
            $rendered = $template->renderEmail($record);
            
            Log::info('📧 Template renderizado do BD', [
                'template_id' => $template->id,
                'subject' => $rendered['subject'],
                'body_length' => strlen($rendered['body_html']),
            ]);
            
            // Extrair nome do destinatário do record (IMPORTANTE para não cair no spam)
            $recipientName = null;
            if (isset($record->full_name)) {
                $recipientName = $record->full_name;
            } elseif (isset($record->name)) {
                $recipientName = $record->name;
            } elseif (isset($record->first_name) && isset($record->last_name)) {
                $recipientName = $record->first_name . ' ' . $record->last_name;
            }
            
            // Enviar email usando HTML DO TEMPLATE (MÉTODO EXATO DO RegisterWizard)
            Mail::send([], [], function ($message) use ($email, $recipientName, $rendered) {
                // IMPORTANTE: Passar nome do destinatário (mesmo método do RegisterWizard)
                $message->to($email, $recipientName)
                        ->subject($rendered['subject'])
                        ->html($rendered['body_html']);
                
                // Configurar from usando settings do tenant
                if ($this->settings->from_email) {
                    $message->from(
                        $this->settings->from_email,
                        $this->settings->from_name ?? $this->settings->from_email
                    );
                }
            });
            
            Log::info('✅ Email enviado via template do BD', [
                'to' => $email,
                'template_id' => $template->id,
                'subject' => $rendered['subject'],
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Email template send failed', [
                'email' => $email,
                'template_id' => $template->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }
    
    /**
     * Configurar SMTP dinamicamente
     */
    protected function configureSMTP()
    {
        // Determinar encryption baseado na porta
        $port = $this->settings->smtp_port ?? 587;
        $encryption = $this->settings->smtp_encryption;
        
        // Se não está configurado, determinar automaticamente
        if (!$encryption) {
            $encryption = ($port == 465) ? 'ssl' : 'tls';
        }
        
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $this->settings->smtp_host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $this->settings->smtp_username,
                'password' => $this->settings->smtp_password,
                'timeout' => null,
                'verify_peer' => false, // Para desenvolvimento
            ],
            'mail.from' => [
                'address' => $this->settings->from_email,
                'name' => $this->settings->from_name ?? $this->settings->from_email,
            ],
        ]);
        
        Log::info('SMTP configured', [
            'host' => $this->settings->smtp_host,
            'port' => $port,
            'encryption' => $encryption,
            'from' => $this->settings->from_email
        ]);
    }
}
