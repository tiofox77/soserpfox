<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\WhatsAppService;
use App\Services\ImmediateNotificationService;
use App\Helpers\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled notifications based on templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔔 Iniciando envio de notificações agendadas...');
        
        $query = NotificationTemplate::where('is_active', true);
        
        if ($this->option('tenant')) {
            $query->where('tenant_id', $this->option('tenant'));
        }
        
        $templates = $query->get();
        
        $this->info("📋 Encontrados {$templates->count()} templates ativos");
        
        $totalSent = 0;
        
        foreach ($templates as $template) {
            $this->line("📤 Processando: {$template->name} [{$template->module}]");
            
            try {
                $sent = $this->processTemplate($template);
                $totalSent += $sent;
                
                if ($sent > 0) {
                    $this->info("   ✅ Enviadas {$sent} notificações");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Erro: " . $e->getMessage());
                Log::error('Notification template error', [
                    'template' => $template->name,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("🎉 Concluído! Total de notificações enviadas: {$totalSent}");
    }
    
    protected function processTemplate(NotificationTemplate $template)
    {
        // Se for evento reminder, usar ImmediateNotificationService
        if ($template->module === 'events' && $template->trigger_event === 'date_approaching') {
            return $this->processEventReminders($template);
        }
        
        $records = $this->getRecordsToNotify($template);
        
        if ($records->isEmpty()) {
            return 0;
        }
        
        $sent = 0;
        
        foreach ($records as $record) {
            // Verificar condições
            if (!$template->meetsConditions($record)) {
                continue;
            }
            
            // Mapear variáveis
            $variables = $template->mapVariables($record);
            
            // Obter destinatários
            $recipients = $this->getRecipient($record, $template->module);
            
            if (!$recipients) {
                continue;
            }
            
            // Enviar WhatsApp para todos os telefones
            if ($template->whatsapp_enabled && !empty($recipients['phones'])) {
                foreach ($recipients['phones'] as $phone) {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($phone);
                    if (PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $this->sendWhatsApp($template, $normalizedPhone, $variables);
                        $sent++;
                    }
                }
            }
            
            // Enviar SMS para todos os telefones
            if ($template->sms_enabled && !empty($recipients['phones'])) {
                foreach ($recipients['phones'] as $phone) {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($phone);
                    if (PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $this->sendSMS($template, $normalizedPhone, $variables);
                        $sent++;
                    }
                }
            }
            
            // Enviar Email para todos os emails
            if ($template->email_enabled && !empty($recipients['emails'])) {
                foreach ($recipients['emails'] as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->sendEmail($template, $email, $variables);
                        $sent++;
                    }
                }
            }
        }
        
        return $sent;
    }
    
    /**
     * Processar lembretes de eventos usando ImmediateNotificationService
     */
    protected function processEventReminders(NotificationTemplate $template)
    {
        $events = $this->getRecordsToNotify($template);
        
        if ($events->isEmpty()) {
            return 0;
        }
        
        $notificationService = new ImmediateNotificationService($template->tenant_id);
        $sent = 0;
        
        foreach ($events as $eventData) {
            try {
                // Buscar evento completo com relacionamentos
                $event = \App\Models\Events\Event::with('technicians', 'venue')
                    ->find($eventData->id);
                
                if (!$event) {
                    continue;
                }
                
                // Buscar técnicos
                $technicians = $event->technicians ?? [];
                
                // Usar serviço de notificações
                $notificationService->notifyEventReminder($event, $technicians->all());
                
                $sent += count($technicians);
                
                Log::info('Event reminder sent', [
                    'event_id' => $event->id,
                    'event_name' => $event->name,
                    'technicians_count' => count($technicians),
                ]);
                
            } catch (\Exception $e) {
                Log::error('Event reminder failed', [
                    'event_id' => $eventData->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $sent;
    }
    
    protected function getRecordsToNotify(NotificationTemplate $template)
    {
        $tableName = $this->getTableName($template->module);
        
        if (!$tableName) {
            return collect();
        }
        
        $query = DB::table($tableName)->where('tenant_id', $template->tenant_id);
        
        // Aplicar filtros baseados no trigger
        switch ($template->trigger_event) {
            case 'date_approaching':
                $query = $this->applyDateApproachingFilter($query, $template);
                break;
                
            case 'created':
                // Para eventos "created", processar apenas registros recentes (última hora)
                $query->where('created_at', '>=', Carbon::now()->subHour());
                break;
        }
        
        return $query->get();
    }
    
    protected function applyDateApproachingFilter($query, NotificationTemplate $template)
    {
        if (!$template->notify_before_minutes) {
            return $query;
        }
        
        // Calcular o momento exato para notificar
        $notifyAt = Carbon::now()->addMinutes($template->notify_before_minutes);
        
        // Buscar campo de data baseado no módulo
        $dateField = $this->getDateField($template->module);
        
        if ($dateField) {
            // Buscar registros cuja data está próxima
            $query->whereBetween($dateField, [
                $notifyAt->format('Y-m-d H:i:s'),
                $notifyAt->addMinutes(30)->format('Y-m-d H:i:s') // Janela de 30 minutos
            ]);
        }
        
        return $query;
    }
    
    protected function getTableName(string $module): ?string
    {
        $tables = [
            'events' => 'events_events',
            'hr' => 'employees',
            'calendar' => 'calendar_events',
            'finance' => 'financial_transactions',
            'crm' => 'crm_leads',
            'projects' => 'projects',
            'tasks' => 'tasks',
        ];
        
        return $tables[$module] ?? null;
    }
    
    protected function getDateField(string $module): ?string
    {
        $fields = [
            'events' => 'start_date',
            'calendar' => 'start_datetime',
            'finance' => 'due_date',
            'hr' => 'start_date',
            'tasks' => 'due_date',
        ];
        
        return $fields[$module] ?? null;
    }
    
    protected function getRecipient($record, string $module): ?array
    {
        // Para cada módulo, definir como obter o destinatário
        switch ($module) {
            case 'events':
                // Buscar técnicos do evento
                $recipients = $this->getEventTechnicians($record);
                
                if (!empty($recipients)) {
                    return $recipients;
                }
                
                // Fallback: organizador ou contato
                $phone = PhoneHelper::normalizeAngolanPhone($record->organizer_phone ?? $record->contact_phone);
                return [
                    'phones' => $phone ? [$phone] : [],
                    'emails' => [$record->organizer_email ?? $record->contact_email],
                ];
                
            case 'hr':
                $phone = PhoneHelper::normalizeAngolanPhone($record->phone);
                return [
                    'phones' => $phone ? [$phone] : [],
                    'emails' => [$record->email],
                ];
                
            case 'calendar':
                $phone = PhoneHelper::normalizeAngolanPhone($record->phone);
                return [
                    'phones' => $phone ? [$phone] : [],
                    'emails' => [$record->email],
                ];
                
            default:
                return null;
        }
    }
    
    protected function getEventTechnicians($event): array
    {
        // Buscar IDs dos técnicos vinculados ao evento
        $staffIds = DB::table('events_event_staff')
            ->where('event_id', $event->id)
            ->pluck('user_id')
            ->toArray();
        
        $phones = [];
        $emails = [];
        
        if (!empty($staffIds)) {
            // Buscar dados dos técnicos da tabela events_technicians
            $technicians = DB::table('events_technicians')
                ->whereIn('user_id', $staffIds)
                ->where('tenant_id', $event->tenant_id)
                ->select('phone', 'email', 'name')
                ->get();
            
            foreach ($technicians as $tech) {
                if ($tech->phone) {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($tech->phone);
                    if (PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $phones[] = $normalizedPhone;
                    }
                }
                if ($tech->email) {
                    $emails[] = $tech->email;
                }
            }
        }
        
        // Se não houver técnicos vinculados, buscar TODOS os técnicos
        if (empty($phones) && empty($emails)) {
            $allTechnicians = DB::table('events_technicians')
                ->where('tenant_id', $event->tenant_id)
                ->select('phone', 'email', 'name')
                ->get();
            
            foreach ($allTechnicians as $tech) {
                if ($tech->phone) {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($tech->phone);
                    if (PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $phones[] = $normalizedPhone;
                    }
                }
                if ($tech->email) {
                    $emails[] = $tech->email;
                }
            }
        }
        
        return [
            'phones' => array_unique($phones),
            'emails' => array_unique($emails),
        ];
    }
    
    protected function sendWhatsApp(NotificationTemplate $template, string $phone, array $variables)
    {
        try {
            $settings = TenantNotificationSetting::getForTenant($template->tenant_id);
            
            if (!$settings->whatsapp_account_sid || !$template->whatsapp_template_sid) {
                return;
            }
            
            $whatsapp = new WhatsAppService(
                $settings->whatsapp_account_sid,
                $settings->whatsapp_auth_token,
                $settings->whatsapp_from_number
            );
            
            $whatsapp->sendTemplate(
                $phone,
                $template->name,
                $variables,
                $template->whatsapp_template_sid
            );
            
            Log::info('Scheduled WhatsApp sent', [
                'template' => $template->name,
                'phone' => $phone
            ]);
            
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'template' => $template->name,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    protected function sendSMS(NotificationTemplate $template, string $phone, array $variables)
    {
        // Similar ao WhatsApp
        $this->sendWhatsApp($template, $phone, $variables);
    }
    
    protected function sendEmail(NotificationTemplate $template, string $email, array $variables)
    {
        // TODO: Implementar envio de email
        Log::info('Email scheduled', [
            'template' => $template->name,
            'email' => $email,
            'variables' => $variables
        ]);
    }
}
