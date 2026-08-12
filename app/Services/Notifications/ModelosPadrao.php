<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Os modelos de notificação que toda a empresa devia ter à partida.
 *
 * O ecrã /notifications/templates estava vazio em todas as empresas menos a
 * primeira: os 24 modelos existiam só na empresa 1, postos à mão, e não havia
 * seeder nenhum. Quem abrisse o ecrã noutra empresa via uma lista em branco e
 * tinha de escrever tudo de raiz — assunto, corpo, variáveis — para doze
 * avisos.
 *
 * O catálogo é o mesmo para todas: cada uma recebe a sua cópia e edita à
 * vontade. `garantirPara()` é idempotente e NÃO toca no que já lá está.
 *
 * O QUE NÃO É COPIADO
 * -------------------
 * Os identificadores de modelo na operadora — `whatsapp_template_sid`,
 * `sms_template_sid`, `email_template_id`. São o registo de cada empresa na
 * Twilio ou no fornecedor de email; copiá-los mandaria uma empresa usar a
 * conta de outra. Cada uma preenche os seus no ecrã de definições.
 */
class ModelosPadrao
{
    /**
     * Módulos com tabela para consultar, e gatilhos com regra de selecção.
     *
     * Serve para o ecrã poder dizer a verdade sobre o que um modelo faz. Um
     * modelo activo que nunca dispara é pior do que não existir: fica a
     * prometer um aviso que não vem, e ninguém percebe porquê.
     */
    private const TABELAS = [
        'events'   => 'events_events',
        'hr'       => 'hr_employees',
        'calendar' => 'calendar_events',
        'finance'  => 'financial_transactions',
        'crm'      => 'crm_leads',
        'projects' => 'projects',
        'tasks'    => 'tasks',
    ];

    /** Os gatilhos que o despacho sabe seleccionar. */
    private const GATILHOS_COM_REGRA = ['created', 'date_approaching'];

    /**
     * Cria os modelos em falta de uma empresa.
     *
     * Compara pelo NOME, não pelo slug: o slug é gerado e pode divergir entre
     * empresas, mas o nome é o que a pessoa vê e o que identifica o modelo.
     *
     * @return int quantos foram criados
     */
    public static function garantirPara(int $tenantId): int
    {
        $existentes = NotificationTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->pluck('name')
            ->flip();

        $criados = 0;

        foreach (self::catalogo() as $modelo) {
            if (isset($existentes[$modelo['name']])) {
                continue;
            }

            NotificationTemplate::withoutGlobalScopes()->create(self::comJsonDescodificado($modelo) + [
                'tenant_id' => $tenantId,
                'slug'      => Str::slug($modelo['name']) . '-' . $tenantId,
            ]);

            $criados++;
        }

        return $criados;
    }

    /**
     * Devolve os campos JSON como array, para o cast do modelo não os
     * codificar uma segunda vez.
     *
     * O catálogo foi extraído da base, onde `variable_mappings` e `conditions`
     * são texto. Passá-los assim a um modelo com cast `array` fazia o Laravel
     * codificar a cadeia OUTRA VEZ — e ao ler de volta vinha uma string em vez
     * de um array, que rebentava o ecrã ao tentar contá-la.
     */
    private static function comJsonDescodificado(array $modelo): array
    {
        foreach (['variable_mappings', 'conditions'] as $campo) {
            if (isset($modelo[$campo]) && is_string($modelo[$campo])) {
                $modelo[$campo] = json_decode($modelo[$campo], true) ?? [];
            }
        }

        return $modelo;
    }

    /**
     * Porque é que este modelo não dispara — ou null se dispara.
     *
     * Duas razões possíveis, e ambas reais neste sistema:
     *
     *   · o módulo não tem tabela para consultar (`tasks`, `calendar`,
     *     `crm` e `projects` não existem nesta base);
     *   · o gatilho não tem regra de selecção. `status_changed` é o caso:
     *     saber que um estado MUDOU exige apanhar a mudança no momento em que
     *     acontece, e não voltar a olhar para a tabela mais tarde — daí não
     *     haver forma de o resolver por consulta periódica.
     */
    public static function porQueNaoDispara(?string $modulo, ?string $gatilho): ?string
    {
        $tabela = self::TABELAS[$modulo] ?? null;

        if (!$tabela || !self::tabelaExiste($tabela)) {
            return 'O módulo "' . $modulo . '" ainda não tem dados neste sistema.';
        }

        if (!in_array($gatilho, self::GATILHOS_COM_REGRA, true)) {
            return 'O gatilho "' . $gatilho . '" precisa de ser disparado no momento da acção, '
                 . 'e ainda não está ligado ao envio automático.';
        }

        return null;
    }

    /** Um modelo do catálogo dispara? */
    public static function dispara(?string $modulo, ?string $gatilho): bool
    {
        return self::porQueNaoDispara($modulo, $gatilho) === null;
    }

    /** Cache por processo: `hasTable` é uma ida à base por chamada. */
    private static array $tabelasVistas = [];

    private static function tabelaExiste(string $tabela): bool
    {
        return self::$tabelasVistas[$tabela] ??= Schema::hasTable($tabela);
    }

    /**
     * O catálogo, sem empresa nenhuma.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            [
                'name' => 'Reunião Agendada - Email',
                'module' => 'calendar',
                'description' => 'Email de reunião',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"titulo":"title","data_inicio":"start_date","hora":"start_time","local":"location","organizador":"organizer.name","descricao":"description","participantes":"attendees_count"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Reunião Agendada - SMS',
                'module' => 'calendar',
                'description' => 'Notificação de reunião',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Reunião agendada: {{ titulo }} em {{ data_inicio }} às {{ hora }}. Local: {{ local }}.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"titulo":"title","data_inicio":"start_date","hora":"start_time","local":"location"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Evento Cancelado - Email',
                'module' => 'events',
                'description' => 'Email de evento cancelado',
                'email_enabled' => 1,
                'email_subject' => 'CANCELADO: {{ event }}',
                'email_body' => 'Informamos que o evento foi CANCELADO:

{{ event }}

Data prevista: {{ date }}
Motivo: {{ motivo }}

Você está dispensado deste compromisso.

Atenciosamente,
Coordenação de Eventos',
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"event":"name","date":"start_date","motivo":"cancellation_reason"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Evento Cancelado - SMS',
                'module' => 'events',
                'description' => 'Notificação de evento cancelado',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'ATENÇÃO: O evento {{ event }} de {{ date }} foi CANCELADO. Você está dispensado.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"event":"name","date":"start_date"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Evento Criado - Email',
                'module' => 'events',
                'description' => 'Email de novo evento',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"event":"name","date":"start_date","hora":"start_time","local":"venue.name","cliente":"client.name","descricao":"description"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Evento Criado - SMS',
                'module' => 'events',
                'description' => 'Notificação de novo evento',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Novo evento: {{ event }} em {{ date }} às {{ hora }}. Local: {{ local }}. Prepare-se!',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"event":"name","date":"start_date","hora":"start_time","local":"venue.name"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Lembrete de Evento - Email',
                'module' => 'events',
                'description' => 'Email de lembrete de evento',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'date_approaching',
                'notify_before_minutes' => 1440,
                'variable_mappings' => '{"event":"name","date":"start_date","hora":"start_time","local":"venue.name","endereco":"venue.address"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Lembrete de Evento - SMS',
                'module' => 'events',
                'description' => 'Lembrete automático de evento',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'LEMBRETE: Evento {{ event }} amanhã às {{ hora }}. Local: {{ local }}. Não esqueça!',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'date_approaching',
                'notify_before_minutes' => 1440,
                'variable_mappings' => '{"event":"name","hora":"start_time","local":"venue.name"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Técnico Designado - Email',
                'module' => 'events',
                'description' => 'Email de designação para evento',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"event":"name","date":"start_date","hora":"start_time","local":"venue.name","cliente":"client.name","funcao":"role"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Técnico Designado - SMS',
                'module' => 'events',
                'description' => 'Notificação de designação para evento',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Você foi escalado para {{ event }} em {{ date }}. Local: {{ local }}. Confirme sua presença!',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"event":"name","date":"start_date","local":"venue.name"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Adiantamento Aprovado - Email',
                'module' => 'hr',
                'description' => 'Email de adiantamento aprovado',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","valor":"amount","data_solicitacao":"created_at","aprovado_por":"approved_by.name"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Adiantamento Aprovado - SMS',
                'module' => 'hr',
                'description' => 'Notificação de adiantamento aprovado',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Olá {{ funcionario }}! Seu pedido de adiantamento no valor de {{ valor }} foi APROVADO. O valor será creditado em breve.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","valor":"amount"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Adiantamento Rejeitado - Email',
                'module' => 'hr',
                'description' => 'Email de adiantamento rejeitado',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","valor":"amount","data_solicitacao":"created_at","motivo":"rejection_reason"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Adiantamento Rejeitado - SMS',
                'module' => 'hr',
                'description' => 'Notificação de adiantamento rejeitado',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Olá {{ funcionario }}. Infelizmente seu pedido de adiantamento de {{ valor }} foi REJEITADO. Motivo: {{ motivo }}. Entre em contato com RH para esclarecimentos.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","valor":"amount","motivo":"rejection_reason"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Férias Aprovadas - Email',
                'module' => 'hr',
                'description' => 'Email de férias aprovadas',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","data_inicio":"start_date","data_fim":"end_date","dias":"total_days"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Férias Aprovadas - SMS',
                'module' => 'hr',
                'description' => 'Notificação de férias aprovadas',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Olá {{ funcionario }}! Suas férias de {{ data_inicio }} a {{ data_fim }} foram APROVADAS. Tenha um ótimo descanso!',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","data_inicio":"start_date","data_fim":"end_date"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Férias Rejeitadas - Email',
                'module' => 'hr',
                'description' => 'Email de férias rejeitadas',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","data_inicio":"start_date","data_fim":"end_date","motivo":"rejection_reason"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Férias Rejeitadas - SMS',
                'module' => 'hr',
                'description' => 'Notificação de férias rejeitadas',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Olá {{ funcionario }}. Seu pedido de férias de {{ data_inicio }} a {{ data_fim }} foi REJEITADO. Motivo: {{ motivo }}. Entre em contato com seu gestor.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'status_changed',
                'variable_mappings' => '{"funcionario":"employee.full_name","data_inicio":"start_date","data_fim":"end_date","motivo":"rejection_reason"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Funcionário Criado - Email',
                'module' => 'hr',
                'description' => 'Email de boas-vindas para novo funcionário',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"funcionario":"full_name","empresa":"tenant.name","cargo":"position","departamento":"department","data_admissao":"hire_date"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Funcionário Criado - SMS',
                'module' => 'hr',
                'description' => 'Notificação de boas-vindas para novo funcionário',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Olá {{ funcionario }}! Seja bem-vindo(a) à {{ empresa }}. Seu cargo é {{ cargo }}. Qualquer dúvida, entre em contato com RH.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"funcionario":"full_name","empresa":"tenant.name","cargo":"position"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Recibo de Pagamento - Email',
                'module' => 'hr',
                'description' => 'Email de recibo disponível',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"funcionario":"employee.full_name","mes":"month","ano":"year","salario_bruto":"gross_salary","descontos":"total_deductions","valor_liquido":"net_salary"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Recibo de Pagamento - SMS',
                'module' => 'hr',
                'description' => 'Notificação de recibo disponível',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Olá {{ funcionario }}! Seu recibo de pagamento referente a {{ mes }}/{{ ano }} está disponível. Valor líquido: {{ valor_liquido }}.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"funcionario":"employee.full_name","mes":"month","ano":"year","valor_liquido":"net_salary"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Tarefa Atribuída - Email',
                'module' => 'tasks',
                'description' => 'Email de nova tarefa',
                'email_enabled' => 1,
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
                'sms_enabled' => 0,
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"tarefa":"title","data_vencimento":"due_date","prioridade":"priority","projeto":"project.name","descricao":"description"}',
                'is_active' => 1,
            ],
            [
                'name' => 'Tarefa Atribuída - SMS',
                'module' => 'tasks',
                'description' => 'Notificação de nova tarefa',
                'email_enabled' => 0,
                'sms_enabled' => 1,
                'sms_body' => 'Nova tarefa atribuída: {{ tarefa }}. Prazo: {{ data_vencimento }}. Prioridade: {{ prioridade }}.',
                'whatsapp_enabled' => 0,
                'trigger_event' => 'created',
                'variable_mappings' => '{"tarefa":"title","data_vencimento":"due_date","prioridade":"priority"}',
                'is_active' => 1,
            ],
        ];
    }
}
