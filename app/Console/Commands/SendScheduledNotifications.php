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
        // O DESVIO QUE AQUI ESTAVA FOI REMOVIDO.
        //
        // Os lembretes de evento (`events` + `date_approaching`) eram
        // encaminhados para o ImmediateNotificationService, um caminho
        // completamente separado — e por isso fora do registo do que já foi
        // enviado. Com o despacho a correr pelo tráfego, de dez em dez
        // minutos, esse caminho reenviaria o mesmo lembrete a cada passagem.
        //
        // Passam pelo caminho normal, que resolve os destinatários da mesma
        // maneira (técnicos designados, ou o cliente do evento) e escreve em
        // notification_sends. O ImmediateNotificationService continua a servir
        // as notificações imediatas, disparadas por acções do utilizador.

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
                        // O id do registo entra na chave do que já foi enviado:
                        // sem ele, avisar sobre a factura A impedia o aviso
                        // sobre a factura B no mesmo dia.
                        $this->sendWhatsApp($template, $normalizedPhone, $variables, $record->id ?? null);
                        $sent++;
                    }
                }
            }
            
            // Enviar SMS para todos os telefones
            if ($template->sms_enabled && !empty($recipients['phones'])) {
                foreach ($recipients['phones'] as $phone) {
                    $normalizedPhone = PhoneHelper::normalizeAngolanPhone($phone);
                    if (PhoneHelper::isValidAngolanPhone($normalizedPhone)) {
                        $this->sendSMS($template, $normalizedPhone, $variables, $record->id ?? null);
                        $sent++;
                    }
                }
            }
            
            // Enviar Email para todos os emails
            if ($template->email_enabled && !empty($recipients['emails'])) {
                foreach ($recipients['emails'] as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->sendEmail($template, $email, $variables, $record->id ?? null);
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
        
        // A tabela pode não existir: `hr` apontava para `employees`, que nunca
        // existiu neste sistema (é `hr_employees`), e `tasks`, `projects`,
        // `crm_leads` e `financial_transactions` também não. Sem esta
        // verificação, doze modelos de RH rebentavam a cada passagem e o erro
        // ficava enterrado no log.
        if (!\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
            Log::info('Modelo de notificação ignorado: tabela inexistente', [
                'template' => $template->name,
                'module'   => $template->module,
                'tabela'   => $tableName,
            ]);

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

            default:
                // NADA, e é deliberado.
                //
                // Aqui não havia `default`, e um gatilho desconhecido — como
                // `status_changed`, que dez dos modelos activos usam — caía sem
                // filtro nenhum: a consulta devolvia a TABELA INTEIRA. Enquanto
                // o email era um TODO e o SMS ia para o WhatsApp, isso não se
                // via. No dia em que os canais passassem a funcionar, uma
                // passagem mandava um aviso a todos os funcionários da empresa.
                //
                // Um gatilho que o código não sabe filtrar não pode significar
                // "toda a gente". Significa ninguém, e fica dito no log.
                Log::info('Modelo de notificação ignorado: gatilho sem regra de selecção', [
                    'template' => $template->name,
                    'gatilho'  => $template->trigger_event,
                ]);

                return collect();
        }

        // Tecto por passagem. Mesmo com a selecção certa, um erro de dados não
        // pode transformar-se em mil emails num pedido de utilizador.
        return $query->limit(self::MAX_REGISTOS_POR_MODELO)->get();
    }

    /**
     * Quantos registos, no máximo, um modelo trata por passagem.
     *
     * Isto corre à boleia do pedido de quem está a trabalhar. O que sobrar fica
     * para a passagem seguinte — a memória do que já saiu garante que não se
     * repete o que já foi.
     */
    private const MAX_REGISTOS_POR_MODELO = 50;
    
    protected function applyDateApproachingFilter($query, NotificationTemplate $template)
    {
        if (!$template->notify_before_minutes) {
            return $query;
        }
        
        $dateField = $this->getDateField($template->module);

        if (!$dateField) {
            return $query;
        }

        // TUDO o que está dentro do prazo de aviso e ainda não passou.
        //
        // Aqui estava uma janela de trinta minutos a começar no instante exacto
        // do aviso: `[agora + antecedência, +30min]`. Com o comando a correr
        // duas vezes por dia, um registo tinha de cair naquela meia hora exacta
        // para ser apanhado — praticamente nada era. E o que escorregasse pela
        // janela nunca mais era avisado, porque a janela avança com o relógio.
        //
        // Assim apanha-se tudo o que está a chegar. O que impede a repetição já
        // não é a estreiteza da janela: é a memória do que saiu
        // (notification_sends), que é o que permite disparar isto pelo tráfego.
        $query->whereBetween($dateField, [
            Carbon::now()->format('Y-m-d H:i:s'),
            Carbon::now()->addMinutes((int) $template->notify_before_minutes)->format('Y-m-d H:i:s'),
        ]);

        return $query;
    }
    
    protected function getTableName(string $module): ?string
    {
        $tables = [
            'events' => 'events_events',
            // `employees` nunca existiu neste sistema — a tabela é
            // `hr_employees`. Os doze modelos de RH rebentavam a cada
            // passagem, e o erro ficava enterrado no log do comando.
            'hr' => 'hr_employees',
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
                // Técnicos do evento primeiro.
                $recipients = $this->getEventTechnicians($record);

                if (!empty($recipients['phones']) || !empty($recipients['emails'])) {
                    return $recipients;
                }

                // Sem técnicos, o contacto do evento é o CLIENTE.
                //
                // Aqui liam-se `organizer_phone`, `contact_phone`,
                // `organizer_email` e `contact_email` — e nenhuma dessas quatro
                // colunas existe em `events_events`. O evento tem `client_id`,
                // e é na ficha do cliente que estão o email e o telefone. O
                // recurso nunca devolveu um destinatário: só saía notificação
                // de evento se houvesse técnicos designados.
                return $this->contactoDoCliente($record->client_id ?? null);

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
    
    /**
     * O email e o telefone de um cliente.
     *
     * Devolve listas vazias — nunca null — para o chamador não ter de
     * distinguir "sem cliente" de "cliente sem contactos".
     */
    protected function contactoDoCliente($clientId): array
    {
        $vazio = ['phones' => [], 'emails' => []];

        if (!$clientId) {
            return $vazio;
        }

        $cliente = DB::table('invoicing_clients')->where('id', $clientId)->first();

        if (!$cliente) {
            return $vazio;
        }

        $telefone = PhoneHelper::normalizeAngolanPhone($cliente->phone ?? null);

        return [
            'phones' => $telefone ? [$telefone] : [],
            'emails' => !empty($cliente->email) ? [$cliente->email] : [],
        ];
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
        
        // O RECURSO "TODOS OS TÉCNICOS" FOI REMOVIDO.
        //
        // Aqui, quando um evento não tinha ninguém designado, ia-se buscar
        // TODOS os técnicos da empresa e avisavam-se todos. É a mesma ideia
        // errada do `switch` sem `default`: a falta de um destinatário
        // concreto a significar "toda a gente".
        //
        // Um evento sem técnico designado não tem a quem lembrar entre os
        // técnicos — quem interessa avisar é o cliente, e é isso que o
        // chamador faz a seguir quando isto vem vazio.

        return [
            'phones' => array_unique($phones),
            'emails' => array_unique($emails),
        ];
    }
    
    /**
     * O envio passou para App\Services\Notifications\EnvioDeNotificacoes.
     *
     * Aqui viviam três defeitos: o email era um TODO que só escrevia no log, o
     * SMS chamava o WhatsApp, e não havia memória do que já tinha saído — cada
     * passagem reenviava tudo a toda a gente.
     *
     * O serviço é partilhado com o despacho por tráfego, para não haver duas
     * versões da mesma regra.
     */
    protected function envio(): \App\Services\Notifications\EnvioDeNotificacoes
    {
        return $this->envio ??= new \App\Services\Notifications\EnvioDeNotificacoes();
    }

    protected ?\App\Services\Notifications\EnvioDeNotificacoes $envio = null;

    protected function sendWhatsAppAntigo(NotificationTemplate $template, string $phone, array $variables)
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
    
    protected function sendWhatsApp(NotificationTemplate $template, string $phone, array $variables, ?int $recordId = null): void
    {
        $this->envio()->enviar($template, 'whatsapp', $phone, $variables, $recordId);
    }

    /**
     * SMS a sério.
     *
     * Isto era `$this->sendWhatsApp(...)` — ligar o SMS num modelo mandava um
     * WhatsApp, e o resumo do comando contava-o como SMS.
     */
    protected function sendSMS(NotificationTemplate $template, string $phone, array $variables, ?int $recordId = null): void
    {
        $this->envio()->enviar($template, 'sms', $phone, $variables, $recordId);
    }

    /** Email a sério. Era um TODO que só escrevia no log. */
    protected function sendEmail(NotificationTemplate $template, string $email, array $variables, ?int $recordId = null): void
    {
        $this->envio()->enviar($template, 'email', $email, $variables, $recordId);
    }

    protected function sendEmailAntigo(NotificationTemplate $template, string $email, array $variables)
    {
        Log::info('Email scheduled', [
            'template' => $template->name,
            'email' => $email,
            'variables' => $variables
        ]);
    }
}
