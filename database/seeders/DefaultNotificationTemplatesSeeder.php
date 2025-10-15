<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;

class DefaultNotificationTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $tenantId = 1; // Altere conforme necessário
        
        $templates = [
            // RECURSOS HUMANOS
            [
                'name' => 'Funcionário Criado - SMS',
                'slug' => 'employee-created-sms',
                'module' => 'hr',
                'description' => 'Notificação de boas-vindas para novo funcionário',
                'trigger_event' => 'created',
                'sms_enabled' => true,
                'sms_body' => 'Olá {{ funcionario }}! Seja bem-vindo(a) à {{ empresa }}. Seu cargo é {{ cargo }}. Qualquer dúvida, entre em contato com RH.',
                'variable_mappings' => [
                    'funcionario' => 'full_name',
                    'empresa' => 'tenant.name',
                    'cargo' => 'position',
                ],
            ],
            [
                'name' => 'Funcionário Criado - Email',
                'slug' => 'employee-created-email',
                'module' => 'hr',
                'description' => 'Email de boas-vindas para novo funcionário',
                'trigger_event' => 'created',
                'email_enabled' => true,
                'email_subject' => 'Bem-vindo(a) à {{ empresa }}',
                'email_body' => 'Olá {{ funcionario }},

Seja muito bem-vindo(a) à {{ empresa }}!

Informações do seu cadastro:
- Cargo: {{ cargo }}
- Departamento: {{ departamento }}
- Data de admissão: {{ data_admissao }}

Em breve você receberá mais informações sobre sua integração.

Atenciosamente,
Equipe de Recursos Humanos',
                'variable_mappings' => [
                    'funcionario' => 'full_name',
                    'empresa' => 'tenant.name',
                    'cargo' => 'position',
                    'departamento' => 'department',
                    'data_admissao' => 'hire_date',
                ],
            ],
            
            // ADIANTAMENTO APROVADO
            [
                'name' => 'Adiantamento Aprovado - SMS',
                'slug' => 'advance-approved-sms',
                'module' => 'hr',
                'description' => 'Notificação de adiantamento aprovado',
                'trigger_event' => 'status_changed',
                'sms_enabled' => true,
                'sms_body' => 'Olá {{ funcionario }}! Seu pedido de adiantamento no valor de {{ valor }} foi APROVADO. O valor será creditado em breve.',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'valor' => 'amount',
                ],
            ],
            [
                'name' => 'Adiantamento Aprovado - Email',
                'slug' => 'advance-approved-email',
                'module' => 'hr',
                'description' => 'Email de adiantamento aprovado',
                'trigger_event' => 'status_changed',
                'email_enabled' => true,
                'email_subject' => 'Adiantamento Aprovado - {{ valor }}',
                'email_body' => 'Olá {{ funcionario }},

Informamos que seu pedido de adiantamento foi APROVADO!

Detalhes:
- Valor: {{ valor }}
- Data da solicitação: {{ data_solicitacao }}
- Aprovado por: {{ aprovado_por }}

O valor será creditado em sua conta em até 2 dias úteis.

Atenciosamente,
Departamento Financeiro',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'valor' => 'amount',
                    'data_solicitacao' => 'created_at',
                    'aprovado_por' => 'approved_by.name',
                ],
            ],
            
            // ADIANTAMENTO REJEITADO
            [
                'name' => 'Adiantamento Rejeitado - SMS',
                'slug' => 'advance-rejected-sms',
                'module' => 'hr',
                'description' => 'Notificação de adiantamento rejeitado',
                'trigger_event' => 'status_changed',
                'sms_enabled' => true,
                'sms_body' => 'Olá {{ funcionario }}. Infelizmente seu pedido de adiantamento de {{ valor }} foi REJEITADO. Motivo: {{ motivo }}. Entre em contato com RH para esclarecimentos.',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'valor' => 'amount',
                    'motivo' => 'rejection_reason',
                ],
            ],
            [
                'name' => 'Adiantamento Rejeitado - Email',
                'slug' => 'advance-rejected-email',
                'module' => 'hr',
                'description' => 'Email de adiantamento rejeitado',
                'trigger_event' => 'status_changed',
                'email_enabled' => true,
                'email_subject' => 'Adiantamento Não Aprovado',
                'email_body' => 'Olá {{ funcionario }},

Informamos que seu pedido de adiantamento não foi aprovado.

Detalhes:
- Valor solicitado: {{ valor }}
- Data da solicitação: {{ data_solicitacao }}
- Motivo: {{ motivo }}

Para mais esclarecimentos, entre em contato com o departamento de RH.

Atenciosamente,
Departamento de Recursos Humanos',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'valor' => 'amount',
                    'data_solicitacao' => 'created_at',
                    'motivo' => 'rejection_reason',
                ],
            ],
            
            // FÉRIAS APROVADAS
            [
                'name' => 'Férias Aprovadas - SMS',
                'slug' => 'leave-approved-sms',
                'module' => 'hr',
                'description' => 'Notificação de férias aprovadas',
                'trigger_event' => 'status_changed',
                'sms_enabled' => true,
                'sms_body' => 'Olá {{ funcionario }}! Suas férias de {{ data_inicio }} a {{ data_fim }} foram APROVADAS. Tenha um ótimo descanso!',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'data_inicio' => 'start_date',
                    'data_fim' => 'end_date',
                ],
            ],
            [
                'name' => 'Férias Aprovadas - Email',
                'slug' => 'leave-approved-email',
                'module' => 'hr',
                'description' => 'Email de férias aprovadas',
                'trigger_event' => 'status_changed',
                'email_enabled' => true,
                'email_subject' => 'Férias Aprovadas - {{ data_inicio }} a {{ data_fim }}',
                'email_body' => 'Olá {{ funcionario }},

Suas férias foram APROVADAS!

Período:
- Início: {{ data_inicio }}
- Fim: {{ data_fim }}
- Total de dias: {{ dias }}

Aproveite seu merecido descanso!

Atenciosamente,
Departamento de Recursos Humanos',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'data_inicio' => 'start_date',
                    'data_fim' => 'end_date',
                    'dias' => 'total_days',
                ],
            ],
            
            // FÉRIAS REJEITADAS
            [
                'name' => 'Férias Rejeitadas - SMS',
                'slug' => 'leave-rejected-sms',
                'module' => 'hr',
                'description' => 'Notificação de férias rejeitadas',
                'trigger_event' => 'status_changed',
                'sms_enabled' => true,
                'sms_body' => 'Olá {{ funcionario }}. Seu pedido de férias de {{ data_inicio }} a {{ data_fim }} foi REJEITADO. Motivo: {{ motivo }}. Entre em contato com seu gestor.',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'data_inicio' => 'start_date',
                    'data_fim' => 'end_date',
                    'motivo' => 'rejection_reason',
                ],
            ],
            [
                'name' => 'Férias Rejeitadas - Email',
                'slug' => 'leave-rejected-email',
                'module' => 'hr',
                'description' => 'Email de férias rejeitadas',
                'trigger_event' => 'status_changed',
                'email_enabled' => true,
                'email_subject' => 'Férias Não Aprovadas',
                'email_body' => 'Olá {{ funcionario }},

Informamos que seu pedido de férias não foi aprovado.

Período solicitado:
- Início: {{ data_inicio }}
- Fim: {{ data_fim }}

Motivo: {{ motivo }}

Por favor, entre em contato com seu gestor para reagendar.

Atenciosamente,
Departamento de Recursos Humanos',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'data_inicio' => 'start_date',
                    'data_fim' => 'end_date',
                    'motivo' => 'rejection_reason',
                ],
            ],
            
            // RECIBO DE PAGAMENTO
            [
                'name' => 'Recibo de Pagamento - SMS',
                'slug' => 'payslip-ready-sms',
                'module' => 'hr',
                'description' => 'Notificação de recibo disponível',
                'trigger_event' => 'created',
                'sms_enabled' => true,
                'sms_body' => 'Olá {{ funcionario }}! Seu recibo de pagamento referente a {{ mes }}/{{ ano }} está disponível. Valor líquido: {{ valor_liquido }}.',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'mes' => 'month',
                    'ano' => 'year',
                    'valor_liquido' => 'net_salary',
                ],
            ],
            [
                'name' => 'Recibo de Pagamento - Email',
                'slug' => 'payslip-ready-email',
                'module' => 'hr',
                'description' => 'Email de recibo disponível',
                'trigger_event' => 'created',
                'email_enabled' => true,
                'email_subject' => 'Recibo de Pagamento - {{ mes }}/{{ ano }}',
                'email_body' => 'Olá {{ funcionario }},

Seu recibo de pagamento está disponível!

Resumo:
- Período: {{ mes }}/{{ ano }}
- Salário Bruto: {{ salario_bruto }}
- Descontos: {{ descontos }}
- Valor Líquido: {{ valor_liquido }}

Acesse o sistema para visualizar os detalhes completos.

Atenciosamente,
Departamento de Recursos Humanos',
                'variable_mappings' => [
                    'funcionario' => 'employee.full_name',
                    'mes' => 'month',
                    'ano' => 'year',
                    'salario_bruto' => 'gross_salary',
                    'descontos' => 'total_deductions',
                    'valor_liquido' => 'net_salary',
                ],
            ],
            
            // EVENTOS - Já existem, mas vou adicionar versões melhoradas
            [
                'name' => 'Evento Criado - SMS',
                'slug' => 'event-created-sms',
                'module' => 'events',
                'description' => 'Notificação de novo evento',
                'trigger_event' => 'created',
                'sms_enabled' => true,
                'sms_body' => 'Novo evento: {{ event }} em {{ date }} às {{ hora }}. Local: {{ local }}. Prepare-se!',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                    'hora' => 'start_time',
                    'local' => 'venue.name',
                ],
            ],
            [
                'name' => 'Evento Criado - Email',
                'slug' => 'event-created-email',
                'module' => 'events',
                'description' => 'Email de novo evento',
                'trigger_event' => 'created',
                'email_enabled' => true,
                'email_subject' => 'Novo Evento: {{ event }}',
                'email_body' => 'Você foi escalado para um novo evento!

Detalhes:
- Evento: {{ event }}
- Data: {{ date }}
- Horário: {{ hora }}
- Local: {{ local }}
- Cliente: {{ cliente }}

Descrição: {{ descricao }}

Atenciosamente,
Equipe de Eventos',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                    'hora' => 'start_time',
                    'local' => 'venue.name',
                    'cliente' => 'client.name',
                    'descricao' => 'description',
                ],
            ],
            
            // LEMBRETE DE EVENTO
            [
                'name' => 'Lembrete de Evento - SMS',
                'slug' => 'event-reminder-sms',
                'module' => 'events',
                'description' => 'Lembrete automático de evento',
                'trigger_event' => 'date_approaching',
                'notify_before_minutes' => 1440, // 24 horas
                'sms_enabled' => true,
                'sms_body' => 'LEMBRETE: Evento {{ event }} amanhã às {{ hora }}. Local: {{ local }}. Não esqueça!',
                'variable_mappings' => [
                    'event' => 'name',
                    'hora' => 'start_time',
                    'local' => 'venue.name',
                ],
            ],
            [
                'name' => 'Lembrete de Evento - Email',
                'slug' => 'event-reminder-email',
                'module' => 'events',
                'description' => 'Email de lembrete de evento',
                'trigger_event' => 'date_approaching',
                'notify_before_minutes' => 1440,
                'email_enabled' => true,
                'email_subject' => 'LEMBRETE: {{ event }} amanhã',
                'email_body' => 'Este é um lembrete do seu evento:

{{ event }}

Data: {{ date }}
Horário: {{ hora }}
Local: {{ local }}
Endereço: {{ endereco }}

Chegue com antecedência!

Atenciosamente,
Equipe de Eventos',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                    'hora' => 'start_time',
                    'local' => 'venue.name',
                    'endereco' => 'venue.address',
                ],
            ],
            
            // TÉCNICO DESIGNADO
            [
                'name' => 'Técnico Designado - SMS',
                'slug' => 'technician-assigned-sms',
                'module' => 'events',
                'description' => 'Notificação de designação para evento',
                'trigger_event' => 'created',
                'sms_enabled' => true,
                'sms_body' => 'Você foi escalado para {{ event }} em {{ date }}. Local: {{ local }}. Confirme sua presença!',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                    'local' => 'venue.name',
                ],
            ],
            [
                'name' => 'Técnico Designado - Email',
                'slug' => 'technician-assigned-email',
                'module' => 'events',
                'description' => 'Email de designação para evento',
                'trigger_event' => 'created',
                'email_enabled' => true,
                'email_subject' => 'Você foi escalado: {{ event }}',
                'email_body' => 'Você foi designado para trabalhar no evento:

{{ event }}

Informações:
- Data: {{ date }}
- Horário: {{ hora }}
- Local: {{ local }}
- Cliente: {{ cliente }}
- Sua função: {{ funcao }}

Por favor, confirme sua disponibilidade.

Atenciosamente,
Coordenação de Eventos',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                    'hora' => 'start_time',
                    'local' => 'venue.name',
                    'cliente' => 'client.name',
                    'funcao' => 'role',
                ],
            ],
            
            // EVENTO CANCELADO
            [
                'name' => 'Evento Cancelado - SMS',
                'slug' => 'event-cancelled-sms',
                'module' => 'events',
                'description' => 'Notificação de evento cancelado',
                'trigger_event' => 'status_changed',
                'sms_enabled' => true,
                'sms_body' => 'ATENÇÃO: O evento {{ event }} de {{ date }} foi CANCELADO. Você está dispensado.',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                ],
            ],
            [
                'name' => 'Evento Cancelado - Email',
                'slug' => 'event-cancelled-email',
                'module' => 'events',
                'description' => 'Email de evento cancelado',
                'trigger_event' => 'status_changed',
                'email_enabled' => true,
                'email_subject' => 'CANCELADO: {{ event }}',
                'email_body' => 'Informamos que o evento foi CANCELADO:

{{ event }}

Data prevista: {{ date }}
Motivo: {{ motivo }}

Você está dispensado deste compromisso.

Atenciosamente,
Coordenação de Eventos',
                'variable_mappings' => [
                    'event' => 'name',
                    'date' => 'start_date',
                    'motivo' => 'cancellation_reason',
                ],
            ],
            
            // TAREFA ATRIBUÍDA
            [
                'name' => 'Tarefa Atribuída - SMS',
                'slug' => 'task-assigned-sms',
                'module' => 'tasks',
                'description' => 'Notificação de nova tarefa',
                'trigger_event' => 'created',
                'sms_enabled' => true,
                'sms_body' => 'Nova tarefa atribuída: {{ tarefa }}. Prazo: {{ data_vencimento }}. Prioridade: {{ prioridade }}.',
                'variable_mappings' => [
                    'tarefa' => 'title',
                    'data_vencimento' => 'due_date',
                    'prioridade' => 'priority',
                ],
            ],
            [
                'name' => 'Tarefa Atribuída - Email',
                'slug' => 'task-assigned-email',
                'module' => 'tasks',
                'description' => 'Email de nova tarefa',
                'trigger_event' => 'created',
                'email_enabled' => true,
                'email_subject' => 'Nova Tarefa: {{ tarefa }}',
                'email_body' => 'Uma nova tarefa foi atribuída a você:

{{ tarefa }}

Detalhes:
- Prazo: {{ data_vencimento }}
- Prioridade: {{ prioridade }}
- Projeto: {{ projeto }}

Descrição:
{{ descricao }}

Acesse o sistema para mais detalhes.

Atenciosamente,
Sistema de Gestão',
                'variable_mappings' => [
                    'tarefa' => 'title',
                    'data_vencimento' => 'due_date',
                    'prioridade' => 'priority',
                    'projeto' => 'project.name',
                    'descricao' => 'description',
                ],
            ],
            
            // REUNIÃO AGENDADA
            [
                'name' => 'Reunião Agendada - SMS',
                'slug' => 'meeting-scheduled-sms',
                'module' => 'calendar',
                'description' => 'Notificação de reunião',
                'trigger_event' => 'created',
                'sms_enabled' => true,
                'sms_body' => 'Reunião agendada: {{ titulo }} em {{ data_inicio }} às {{ hora }}. Local: {{ local }}.',
                'variable_mappings' => [
                    'titulo' => 'title',
                    'data_inicio' => 'start_date',
                    'hora' => 'start_time',
                    'local' => 'location',
                ],
            ],
            [
                'name' => 'Reunião Agendada - Email',
                'slug' => 'meeting-scheduled-email',
                'module' => 'calendar',
                'description' => 'Email de reunião',
                'trigger_event' => 'created',
                'email_enabled' => true,
                'email_subject' => 'Reunião: {{ titulo }}',
                'email_body' => 'Você foi convidado para uma reunião:

{{ titulo }}

Quando: {{ data_inicio }} às {{ hora }}
Onde: {{ local }}
Organizador: {{ organizador }}

Agenda:
{{ descricao }}

Participantes: {{ participantes }}

Atenciosamente,
Sistema de Gestão',
                'variable_mappings' => [
                    'titulo' => 'title',
                    'data_inicio' => 'start_date',
                    'hora' => 'start_time',
                    'local' => 'location',
                    'organizador' => 'organizer.name',
                    'descricao' => 'description',
                    'participantes' => 'attendees_count',
                ],
            ],
        ];
        
        foreach ($templates as $templateData) {
            NotificationTemplate::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'slug' => $templateData['slug'],
                ],
                array_merge(['tenant_id' => $tenantId, 'is_active' => true], $templateData)
            );
        }
        
        $this->command->info('✅ ' . count($templates) . ' templates padrão criados com sucesso!');
    }
}
