<?php

namespace App\Services\Workshop;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\TenantNotificationSetting;
use App\Models\Workshop\Appointment;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Services\Notifications\EnvioDeNotificacoes;
use Illuminate\Support\Facades\Log;

/**
 * OS AVISOS AO CLIENTE DA OFICINA (15/09/2026, OF-10).
 *
 * «A sua viatura está pronta», «o orçamento espera a sua aprovação» — por email
 * e por SMS, pelos canais que A EMPRESA configurou no módulo Notificações. Sem
 * o módulo, ou sem SMTP/SMS configurado, não sai nada (regra do dono: SMS aos
 * clientes só com o módulo Notificações e o SMS configurado).
 *
 * Os textos são MODELOS DE NOTIFICAÇÃO normais (módulo `workshop`): nascem com
 * a primeira ordem e a empresa muda-os, desliga-os ou tira um canal no ecrã
 * Modelos de Notificação. O agendador das Notificações não lhes toca (não têm
 * tabela nem gatilho de selecção): saem só daqui, quando a coisa acontece.
 *
 * Nada aqui rebenta a ordem: envia-se DEPOIS da resposta, e um aviso que falha
 * fica no log.
 *
 * A AGENDA E O DONO DA EMPRESA (28/09/2026). As marcações não avisavam
 * ninguém, e o dono da empresa não sabia de nada — nem quando o cliente
 * respondia ao orçamento. Agora cada acontecimento tem dois modelos: o do
 * cliente (`chave`) e o do dono (`dono-chave`), que vai ao responsável da
 * empresa (ContactoDeFacturacao: o primeiro utilizador activo, senão os
 * contactos da empresa). Quem fez a acção não é avisado do que fez: se foi o
 * próprio dono a marcar, o aviso dele não sai.
 */
class AvisosDaOficina
{
    public const MODELOS = [
        'pronta' => [
            'estado' => 'completed',
            'name' => 'Oficina — Viatura pronta',
            'description' => 'Quando a ordem de serviço passa a Concluída.',
            'email_subject' => 'A sua viatura {{matricula}} está pronta',
            'email_body' => "Olá {{cliente}},\n\nA sua viatura {{viatura}} ({{matricula}}) está pronta para levantar.\nOrdem de serviço: {{ordem}}\nTotal: {{total}} Kz\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a sua viatura {{matricula}} esta pronta para levantar. Ordem {{ordem}}, total {{total}} Kz.',
            'is_active' => true,
        ],
        'pecas' => [
            'estado' => 'waiting_parts',
            'name' => 'Oficina — À espera de peças',
            'description' => 'Quando a ordem de serviço fica à espera de peças.',
            'email_subject' => 'A sua viatura {{matricula}} aguarda peças',
            'email_body' => "Olá {{cliente}},\n\nA reparação da sua viatura {{viatura}} ({{matricula}}) está à espera de peças. Avisamos assim que chegarem.\nOrdem de serviço: {{ordem}}\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a reparacao da viatura {{matricula}} aguarda pecas. Avisamos quando chegarem. Ordem {{ordem}}.',
            'is_active' => false,
        ],
        'entregue' => [
            'estado' => 'delivered',
            'name' => 'Oficina — Viatura entregue',
            'description' => 'Quando a viatura é entregue ao cliente.',
            'email_subject' => 'Obrigado por confiar na {{empresa}}',
            'email_body' => "Olá {{cliente}},\n\nObrigado por nos confiar a sua viatura {{matricula}}. Esperamos vê-lo em breve.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: obrigado por nos confiar a viatura {{matricula}}. Ate breve!',
            'is_active' => false,
        ],
        'orcamento' => [
            'estado' => null,
            'name' => 'Oficina — Orçamento para aprovar',
            'description' => 'Quando a oficina pede ao cliente que aprove o orçamento.',
            'email_subject' => 'O orçamento da viatura {{matricula}} espera a sua aprovação',
            'email_body' => "Olá {{cliente}},\n\nPreparámos o orçamento da sua viatura {{viatura}} ({{matricula}}). Veja, aprove ou recuse cada trabalho aqui:\n{{link}}\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: o orcamento da viatura {{matricula}} espera a sua aprovacao: {{link}}',
            'is_active' => true,
        ],
        /*
         * OS LEMBRETES (OF-11) — saem do ecrã Lembretes de Manutenção, por
         * clique ou sozinhos se a oficina o ligar. `{{quando}}` é «aos 60 000 km
         * ou a 20/10/2026»; `{{data}}` é a validade do documento.
         */
        'revisao' => [
            'estado' => null,
            'name' => 'Oficina — Revisão a chegar',
            'description' => 'Lembrete da próxima revisão da viatura, por km ou por data.',
            'email_subject' => 'Está na hora da revisão da viatura {{matricula}}',
            'email_body' => "Olá {{cliente}},\n\nA sua viatura {{viatura}} ({{matricula}}) está a chegar à revisão: {{quando}}.\nMarque connosco por telefone ou responda a este email.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a viatura {{matricula}} esta a chegar a revisao ({{quando}}). Marque connosco.',
            'is_active' => true,
        ],
        'seguro' => [
            'estado' => null,
            'name' => 'Oficina — Seguro a caducar',
            'description' => 'Quando o seguro da viatura está a caducar.',
            'email_subject' => 'O seguro da viatura {{matricula}} caduca a {{data}}',
            'email_body' => "Olá {{cliente}},\n\nO seguro da sua viatura {{viatura}} ({{matricula}}) caduca a {{data}}. Não se esqueça de o renovar.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: o seguro da viatura {{matricula}} caduca a {{data}}. Nao se esqueca de renovar.',
            'is_active' => true,
        ],
        'inspeccao' => [
            'estado' => null,
            'name' => 'Oficina — Inspecção a caducar',
            'description' => 'Quando a inspecção periódica da viatura está a caducar.',
            'email_subject' => 'A inspecção da viatura {{matricula}} caduca a {{data}}',
            'email_body' => "Olá {{cliente}},\n\nA inspecção periódica da sua viatura {{viatura}} ({{matricula}}) caduca a {{data}}. Podemos preparar a viatura para a inspecção.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a inspeccao da viatura {{matricula}} caduca a {{data}}. Podemos preparar a viatura.',
            'is_active' => true,
        ],
        'livrete' => [
            'estado' => null,
            'name' => 'Oficina — Livrete a caducar',
            'description' => 'Quando o livrete da viatura está a caducar.',
            'email_subject' => 'O livrete da viatura {{matricula}} caduca a {{data}}',
            'email_body' => "Olá {{cliente}},\n\nO livrete da sua viatura {{viatura}} ({{matricula}}) caduca a {{data}}.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: o livrete da viatura {{matricula}} caduca a {{data}}.',
            'is_active' => true,
        ],
        // OF-14: o convite para avaliar, quando a viatura é entregue.
        'inquerito' => [
            'estado' => null,
            'name' => 'Oficina — Inquérito de satisfação',
            'description' => 'Depois da entrega, o link para o cliente avaliar o serviço.',
            'email_subject' => 'Como correu o serviço da viatura {{matricula}}?',
            'email_body' => "Olá {{cliente}},\n\nObrigado por confiar na {{empresa}}. Em 30 segundos diga-nos como correu o serviço da sua viatura {{matricula}}:\n{{link}}\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: obrigado! Avalie o servico da viatura {{matricula}} em 30 segundos: {{link}}',
            'is_active' => true,
        ],
        /*
         * A AGENDA (28/09/2026). `{{quando}}` é «29/09/2026 09:00» (sem «às»:
         * o SMS vai sem acentos); `{{servico}}` o que se marcou.
         */
        'marcacao' => [
            'estado' => null,
            'name' => 'Oficina — Marcação feita',
            'description' => 'Quando a viatura é marcada na agenda da oficina.',
            'email_subject' => 'A sua viatura {{matricula}} está marcada para {{quando}}',
            'email_body' => "Olá {{cliente}},\n\nA sua viatura {{matricula}} ficou marcada para {{quando}}.\nServiço: {{servico}}\n\nSe não puder vir, avise-nos.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: viatura {{matricula}} marcada para {{quando}} ({{servico}}). Se nao puder vir, avise-nos.',
            'is_active' => true,
        ],
        'marcacao-alterada' => [
            'estado' => null,
            'name' => 'Oficina — Marcação alterada',
            'description' => 'Quando a data ou a hora da marcação muda.',
            'email_subject' => 'A marcação da viatura {{matricula}} mudou para {{quando}}',
            'email_body' => "Olá {{cliente}},\n\nA marcação da sua viatura {{matricula}} mudou. Nova data: {{quando}}.\nServiço: {{servico}}\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a marcacao da viatura {{matricula}} mudou para {{quando}}.',
            'is_active' => true,
        ],
        'marcacao-confirmada' => [
            'estado' => null,
            'name' => 'Oficina — Marcação confirmada',
            'description' => 'Quando a oficina confirma a marcação.',
            'email_subject' => 'Marcação confirmada: viatura {{matricula}} a {{quando}}',
            'email_body' => "Olá {{cliente}},\n\nConfirmamos a marcação da sua viatura {{matricula}} para {{quando}}.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: confirmada a marcacao da viatura {{matricula}} para {{quando}}.',
            'is_active' => false,
        ],
        'marcacao-cancelada' => [
            'estado' => null,
            'name' => 'Oficina — Marcação cancelada',
            'description' => 'Quando a marcação é cancelada.',
            'email_subject' => 'A marcação da viatura {{matricula}} foi cancelada',
            'email_body' => "Olá {{cliente}},\n\nA marcação da sua viatura {{matricula}} para {{quando}} foi cancelada. Para marcar outra data, contacte-nos.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a marcacao da viatura {{matricula}} para {{quando}} foi cancelada. Contacte-nos para remarcar.',
            'is_active' => true,
        ],
        'marcacao-lembrete' => [
            'estado' => null,
            'name' => 'Oficina — Lembrete da marcação',
            'description' => 'Na véspera, o lembrete da marcação.',
            'email_subject' => 'Lembrete: a viatura {{matricula}} está marcada para {{quando}}',
            'email_body' => "Olá {{cliente}},\n\nLembramos que a sua viatura {{matricula}} está marcada para {{quando}}.\nServiço: {{servico}}\n\nSe não puder vir, avise-nos.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: lembrete - viatura {{matricula}} marcada para {{quando}}. Se nao puder vir, avise-nos.',
            'is_active' => true,
        ],
        'marcacao-faltou' => [
            'estado' => null,
            'name' => 'Oficina — Faltou à marcação',
            'description' => 'Quando o cliente não aparece: convite para remarcar.',
            'email_subject' => 'Sentimos a sua falta — viatura {{matricula}}',
            'email_body' => "Olá {{cliente}},\n\nA sua viatura {{matricula}} estava marcada para {{quando}} e não a recebemos. Quer marcar outra data?\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a viatura {{matricula}} estava marcada para {{quando}}. Quer marcar outra data?',
            'is_active' => false,
        ],
        // A viatura entrou: a ordem de serviço foi aberta.
        'aberta' => [
            'estado' => null,
            'name' => 'Oficina — Viatura recebida',
            'description' => 'Quando a ordem de serviço é aberta (a viatura entrou na oficina).',
            'email_subject' => 'Recebemos a sua viatura {{matricula}}',
            'email_body' => "Olá {{cliente}},\n\nRecebemos a sua viatura {{viatura}} ({{matricula}}).\nOrdem de serviço: {{ordem}}\n\nAvisamos quando estiver pronta.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: recebemos a viatura {{matricula}}. Ordem {{ordem}}. Avisamos quando estiver pronta.',
            'is_active' => true,
        ],

        /*
         * OS AVISOS AO DONO DA EMPRESA (28/09/2026). Por email; o SMS nasce
         * desligado (custa, e a oficina faz muitas marcações por dia) menos
         * na resposta do cliente ao orçamento, que espera por alguém.
         */
        'dono-marcacao' => [
            'estado' => null, 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Nova marcação',
            'description' => 'Aviso ao responsável da empresa: uma viatura foi marcada.',
            'email_subject' => 'Nova marcação: {{matricula}} para {{quando}}',
            'email_body' => "Foi marcada a viatura {{matricula}} para {{quando}}.\nCliente: {{cliente}} {{telefone}}\nServiço: {{servico}}\nMarcada por: {{quem}}\n\n{{link}}",
            'sms_body' => '{{empresa}}: nova marcacao {{matricula}} para {{quando}} ({{cliente}}).',
            'is_active' => true,
        ],
        'dono-marcacao-alterada' => [
            'estado' => null, 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Marcação alterada',
            'description' => 'Aviso ao responsável da empresa: a data de uma marcação mudou.',
            'email_subject' => 'Marcação alterada: {{matricula}} passou para {{quando}}',
            'email_body' => "A marcação da viatura {{matricula}} ({{cliente}}) passou de {{antes}} para {{quando}}.\nAlterada por: {{quem}}\n\n{{link}}",
            'sms_body' => '{{empresa}}: marcacao {{matricula}} passou para {{quando}}.',
            'is_active' => true,
        ],
        'dono-marcacao-cancelada' => [
            'estado' => null, 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Marcação cancelada',
            'description' => 'Aviso ao responsável da empresa: uma marcação foi cancelada.',
            'email_subject' => 'Marcação cancelada: {{matricula}} de {{quando}}',
            'email_body' => "A marcação da viatura {{matricula}} ({{cliente}}) para {{quando}} foi cancelada.\nCancelada por: {{quem}}\n\n{{link}}",
            'sms_body' => '{{empresa}}: cancelada a marcacao {{matricula}} de {{quando}}.',
            'is_active' => true,
        ],
        'dono-marcacao-faltou' => [
            'estado' => null, 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Cliente faltou',
            'description' => 'Aviso ao responsável da empresa: o cliente não apareceu à marcação.',
            'email_subject' => 'Faltou à marcação: {{matricula}} de {{quando}}',
            'email_body' => "O cliente {{cliente}} {{telefone}} não apareceu com a viatura {{matricula}} marcada para {{quando}}.\n\n{{link}}",
            'sms_body' => '{{empresa}}: {{cliente}} faltou a marcacao {{matricula}} de {{quando}}.',
            'is_active' => true,
        ],
        'dono-aberta' => [
            'estado' => null, 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Ordem aberta',
            'description' => 'Aviso ao responsável da empresa: entrou uma viatura e foi aberta a ordem.',
            'email_subject' => 'Ordem {{ordem}} aberta: {{matricula}}',
            'email_body' => "Foi aberta a ordem de serviço {{ordem}} para a viatura {{viatura}} ({{matricula}}).\nCliente: {{cliente}} {{telefone}}\n\n{{link}}",
            'sms_body' => '{{empresa}}: aberta a ordem {{ordem}} ({{matricula}}).',
            'is_active' => true,
        ],
        'dono-orcamento-respondido' => [
            'estado' => null, 'para' => 'dono', 'sms' => true,
            'name' => 'Oficina (dono) — Cliente respondeu ao orçamento',
            'description' => 'Aviso ao responsável da empresa: o cliente aprovou ou recusou o orçamento pelo link.',
            'email_subject' => '{{nome}} respondeu ao orçamento da viatura {{matricula}}',
            'email_body' => "{{nome}} respondeu ao orçamento da ordem {{ordem}} ({{matricula}}): {{aprovadas}} trabalho(s) aprovado(s), {{recusadas}} recusado(s).\nTotal da ordem: {{total}} Kz\n\n{{link}}",
            'sms_body' => '{{empresa}}: {{nome}} respondeu ao orcamento {{ordem}} ({{matricula}}): {{aprovadas}} aprovado(s), {{recusadas}} recusado(s).',
            'is_active' => true,
        ],
        'dono-pronta' => [
            'estado' => 'completed', 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Viatura pronta',
            'description' => 'Aviso ao responsável da empresa: uma ordem foi concluída.',
            'email_subject' => 'Ordem {{ordem}} concluída: {{matricula}} pronta',
            'email_body' => "A ordem {{ordem}} da viatura {{matricula}} ({{cliente}}) foi concluída e a viatura está pronta para levantar.\nTotal: {{total}} Kz\n\n{{link}}",
            'sms_body' => '{{empresa}}: ordem {{ordem}} concluida, {{matricula}} pronta. Total {{total}} Kz.',
            'is_active' => true,
        ],
        'dono-entregue' => [
            'estado' => 'delivered', 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Viatura entregue',
            'description' => 'Aviso ao responsável da empresa: a viatura foi entregue ao cliente.',
            'email_subject' => 'Viatura {{matricula}} entregue (ordem {{ordem}})',
            'email_body' => "A viatura {{matricula}} ({{cliente}}) foi entregue. Ordem {{ordem}}, total {{total}} Kz.\n\n{{link}}",
            'sms_body' => '{{empresa}}: entregue a viatura {{matricula}} (ordem {{ordem}}).',
            'is_active' => true,
        ],
        'dono-avaliacao' => [
            'estado' => null, 'para' => 'dono', 'sms' => false,
            'name' => 'Oficina (dono) — Avaliação do cliente',
            'description' => 'Aviso ao responsável da empresa: o cliente avaliou o serviço.',
            'email_subject' => '{{cliente}} avaliou o serviço da viatura {{matricula}}: {{nota}}/5',
            'email_body' => "{{cliente}} avaliou o serviço da ordem {{ordem}} ({{matricula}}) com {{nota}} de 5.\nRecomenda: {{recomenda}}\nComentário: {{comentario}}\n\n{{link}}",
            'sms_body' => '{{empresa}}: {{cliente}} avaliou o servico {{ordem}} ({{matricula}}) com {{nota}}/5.',
            'is_active' => true,
        ],

        // OF-12: o que ficou por fazer numa visita.
        'recomendacao' => [
            'estado' => null,
            'name' => 'Oficina — Trabalhos recomendados',
            'description' => 'Os trabalhos que o cliente deixou para depois, na data de voltar a propor.',
            'email_subject' => 'Trabalhos recomendados para a viatura {{matricula}}',
            'email_body' => "Olá {{cliente}},\n\nNa última visita da sua viatura {{viatura}} ({{matricula}}) ficaram trabalhos por fazer: {{trabalhos}}.\nMarque connosco por telefone ou responda a este email.\n\n{{empresa}}",
            'sms_body' => '{{empresa}}: a viatura {{matricula}} tem trabalhos recomendados por fazer: {{trabalhos}}. Marque connosco.',
            'is_active' => true,
        ],
    ];

    /** Os avisos que dependem do estado da ordem, por estado. */
    public static function chaveDoEstado(string $estado): ?string
    {
        foreach (self::MODELOS as $chave => $m) {
            if ($m['estado'] === $estado) {
                return $chave;
            }
        }

        return null;
    }

    /** Todos os avisos de um estado: o do cliente e o do dono. @return list<string> */
    public static function chavesDoEstado(string $estado): array
    {
        return array_keys(array_filter(self::MODELOS, fn ($m) => $m['estado'] === $estado));
    }

    /** A empresa tem o módulo Notificações e pelo menos um canal para clientes? */
    public static function activos(int $tenantId): bool
    {
        if (! Tenant::find($tenantId)?->hasModule('notifications')) {
            return false;
        }

        $d = TenantNotificationSetting::getForTenant($tenantId);

        return (bool) (($d->sms_enabled ?? false) || (($d->email_enabled ?? false) && filled($d->smtp_host ?? null)));
    }

    public static function garantirModelos(int $tenantId): void
    {
        foreach (self::MODELOS as $chave => $m) {
            // Com os apagados: apagar o modelo é desligar o aviso, e o slug não pode nascer duas vezes.
            NotificationTemplate::withoutGlobalScopes()->withTrashed()->firstOrCreate(
                ['slug' => "oficina-{$chave}-{$tenantId}"],
                [
                    'tenant_id' => $tenantId,
                    'name' => __($m['name']),
                    'module' => 'workshop',
                    'description' => __($m['description']),
                    'email_enabled' => true,
                    'sms_enabled' => $m['sms'] ?? true,
                    'whatsapp_enabled' => false,
                    'email_subject' => __($m['email_subject']),
                    'email_body' => __($m['email_body']),
                    'sms_body' => __($m['sms_body']),
                    'trigger_event' => $m['estado'] ? 'status_changed' : 'custom',
                    'conditions' => $m['estado'] ? ['estado' => $m['estado']] : [],
                    'is_active' => $m['is_active'],
                ],
            );
        }
    }

    /** A ordem mudou de estado — avisa, se houver aviso para esse estado. */
    public static function estadoMudou(WorkOrder $ordem, string $estado): void
    {
        foreach (self::chavesDoEstado($estado) as $chave) {
            if (str_starts_with($chave, 'dono-')) {
                self::donoDaOrdem($ordem, $chave);
            } else {
                self::depois(fn () => self::avisar($ordem, $chave));
            }
        }
    }

    /**
     * A ORDEM FOI ABERTA — a viatura entrou. Não se diz «recebemos» a uma
     * ordem agendada (a viatura ainda não veio) nem a uma já cancelada.
     */
    public static function ordemAberta(WorkOrder $ordem): void
    {
        if (in_array($ordem->status, ['scheduled', 'cancelled'], true)) {
            return;
        }

        self::depois(fn () => self::avisar($ordem, 'aberta'));
        self::donoDaOrdem($ordem, 'dono-aberta');
    }

    /**
     * UMA MARCAÇÃO MUDOU — `marcacao`, `marcacao-alterada`,
     * `marcacao-confirmada`, `marcacao-cancelada`, `marcacao-faltou`: o
     * cliente e, se houver modelo para isso, o dono.
     */
    public static function marcacao(Appointment $marcacao, string $chave, array $extra = []): void
    {
        $extra += ['quem' => auth()->user()?->name ?? '—'];

        self::depois(fn () => self::avisarMarcacao($marcacao, $chave, $extra));

        if (isset(self::MODELOS["dono-{$chave}"])) {
            [$telefone] = self::contactosDaMarcacao($marcacao);
            self::dono((int) $marcacao->tenant_id, "dono-{$chave}", self::variaveisDaMarcacao($marcacao) + [
                'telefone' => $telefone ? "({$telefone})" : '',
                'link' => self::ligacao('workshop.schedule'),
            ] + $extra, (int) $marcacao->id);
        }
    }

    /**
     * O LEMBRETE NA VÉSPERA — as marcações que começam daqui a 2 a 26 horas e
     * ainda não foram lembradas. Corre com as notificações agendadas (como os
     * lembretes de manutenção); cada marcação é lembrada uma vez só.
     */
    public static function lembrarMarcacoes(int $tenantId, int $limite = 50): int
    {
        if (! Tenant::find($tenantId)?->hasModule('oficina') || ! self::activos($tenantId)) {
            return 0;
        }

        $marcacoes = Appointment::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereIn('status', Appointment::OCUPAM)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now()->addHours(2), now()->addHours(26)])
            ->orderBy('starts_at')->limit($limite)->get();

        $avisadas = 0;

        foreach ($marcacoes as $m) {
            // Marca-se ANTES de enviar: duas passagens ao mesmo tempo não lembram duas vezes.
            if (! Appointment::withoutGlobalScopes()->whereKey($m->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()])) {
                continue;
            }

            $avisadas += self::avisarMarcacao($m, 'marcacao-lembrete') ? 1 : 0;
        }

        return $avisadas;
    }

    /** @return list<string> os canais por onde saiu */
    public static function avisarMarcacao(Appointment $marcacao, string $chave, array $extra = []): array
    {
        try {
            [$telefone, $email] = self::contactosDaMarcacao($marcacao);

            return self::enviarModelo((int) $marcacao->tenant_id, $chave, $telefone, $email,
                self::variaveisDaMarcacao($marcacao) + $extra, (int) $marcacao->id);
        } catch (\Throwable $e) {
            Log::warning('Aviso da marcação não enviado', ['marcacao' => $marcacao->id, 'aviso' => $chave, 'erro' => $e->getMessage()]);

            return [];
        }
    }

    /** O telefone da marcação primeiro (é o que o cliente deu para esta vez); o resto da viatura. */
    private static function contactosDaMarcacao(Appointment $marcacao): array
    {
        $v = $marcacao->vehicle_id ? Vehicle::withoutGlobalScopes()->find($marcacao->vehicle_id) : null;
        [$telefone, $email] = $v ? self::contactos($v) : [null, null];

        return [$marcacao->customer_phone ?: $telefone, $email];
    }

    private static function variaveisDaMarcacao(Appointment $marcacao): array
    {
        $v = $marcacao->vehicle_id ? Vehicle::withoutGlobalScopes()->find($marcacao->vehicle_id) : null;
        $daViatura = $v ? self::variaveis($v) : [];

        return [
            'cliente' => $marcacao->customer_name ?: ($daViatura['cliente'] ?? ''),
            'matricula' => $marcacao->plate ?: ($daViatura['matricula'] ?? ''),
            'viatura' => $daViatura['viatura'] ?? '',
            'empresa' => Tenant::find($marcacao->tenant_id)?->name,
            'quando' => $marcacao->starts_at?->format('d/m/Y H:i') ?? '',
            'servico' => $marcacao->service ?: __('Revisão / reparação'),
        ];
    }

    /** O aviso ao dono sobre uma ordem: as variáveis da ordem e a ligação para ela. */
    public static function donoDaOrdem(WorkOrder $ordem, string $chave, array $extra = []): void
    {
        $ordem->loadMissing(['vehicle' => fn ($q) => $q->withoutGlobalScopes()]);

        if (! $ordem->vehicle) {
            return;
        }

        [$telefone] = self::contactos($ordem->vehicle);

        self::dono((int) $ordem->tenant_id, $chave, self::variaveis($ordem->vehicle) + [
            'ordem' => $ordem->order_number,
            'total' => number_format((float) $ordem->total, 2, ',', '.'),
            'estado' => __(OrdensDeServico::ESTADOS[$ordem->status] ?? $ordem->status),
            'telefone' => $telefone ? "({$telefone})" : '',
            'link' => self::ligacao('workshop.work-orders', ['ordem' => $ordem->id]),
        ] + $extra, (int) $ordem->id, $ordem);
    }

    /**
     * O AVISO AO DONO DA EMPRESA. Quem fez a acção não é avisado do que fez
     * (o autor lê-se agora; o envio é depois da resposta).
     */
    public static function dono(int $tenantId, string $chave, array $variaveis, int $registo, ?WorkOrder $ordem = null): void
    {
        $autor = auth()->id();

        self::depois(fn () => self::avisarDono($tenantId, $chave, $variaveis, $registo, $ordem, $autor));
    }

    /** @return list<string> os canais por onde saiu */
    public static function avisarDono(int $tenantId, string $chave, array $variaveis, int $registo, ?WorkOrder $ordem = null, ?int $autor = null): array
    {
        try {
            $empresa = Tenant::find($tenantId);
            if (! $empresa) {
                return [];
            }

            $dono = app(\App\Services\Billing\ContactoDeFacturacao::class)->para($empresa);

            if ($autor && $dono['user_id'] === $autor) {
                return [];
            }

            $sairam = self::enviarModelo($tenantId, $chave, $dono['telefone'], $dono['email'],
                ['empresa' => $empresa->name, 'dono' => $dono['nome']] + $variaveis, $registo);

            if ($sairam && $ordem) {
                $modelo = __(self::MODELOS[$chave]['name'] ?? $chave);
                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                    __('Aviso «:aviso» enviado ao responsável da empresa por :canais.', ['aviso' => $modelo, 'canais' => implode(' e ', $sairam)]));
            }

            return $sairam;
        } catch (\Throwable $e) {
            Log::warning('Aviso da oficina ao dono não enviado', ['tenant' => $tenantId, 'aviso' => $chave, 'erro' => $e->getMessage()]);

            return [];
        }
    }

    /** A morada de um ecrã da oficina, para o dono abrir do email. */
    private static function ligacao(string $rota, array $parametros = []): string
    {
        try {
            return route($rota, $parametros);
        } catch (\Throwable) {
            return rtrim((string) config('app.url'), '/');
        }
    }

    /** A oficina pediu a aprovação do orçamento. */
    public static function orcamento(WorkOrder $ordem, string $link): void
    {
        self::depois(fn () => self::avisar($ordem, 'orcamento', ['link' => $link]));
    }

    /** OF-14: o convite para avaliar o serviço; marca quando saiu. */
    public static function inquerito(WorkOrder $ordem, \App\Models\Workshop\WorkOrderSurvey $inquerito, string $link): void
    {
        self::depois(function () use ($ordem, $inquerito, $link) {
            if (self::avisar($ordem, 'inquerito', ['link' => $link])) {
                $inquerito->update(['sent_at' => now()]);
            }
        });
    }

    /** Depois da resposta, num pedido; logo, na consola. */
    private static function depois(\Closure $trabalho): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $trabalho();

            return;
        }

        // Uma vez só: a lista de «terminating» fica na aplicação e corre a cada fim de pedido.
        $feito = false;
        app()->terminating(function () use ($trabalho, &$feito) {
            if (! $feito) {
                $feito = true;
                $trabalho();
            }
        });
    }

    /**
     * @return list<string> os canais por onde saiu
     */
    public static function avisar(WorkOrder $ordem, string $chave, array $extra = []): array
    {
        $ordem->loadMissing(['vehicle' => fn ($q) => $q->withoutGlobalScopes()]);

        return self::avisarViatura($ordem->vehicle, $chave, [
            'ordem' => $ordem->order_number,
            'total' => number_format((float) $ordem->total, 2, ',', '.'),
            'estado' => __(OrdensDeServico::ESTADOS[$ordem->status] ?? $ordem->status),
        ] + $extra, $ordem);
    }

    /**
     * O aviso a partir da viatura — os lembretes (OF-11) não têm ordem.
     *
     * @param  list<string>|null  $so  só estes canais ('sms', 'email'); nulo = os dois
     * @return list<string> os canais por onde saiu
     */
    public static function avisarViatura(?Vehicle $v, string $chave, array $extra = [], ?WorkOrder $ordem = null, ?array $so = null): array
    {
        if (! $v) {
            return [];
        }

        try {
            [$telefone, $email] = self::contactos($v);

            $sairam = self::enviarModelo((int) $v->tenant_id, $chave, $telefone, $email,
                self::variaveis($v) + $extra, $ordem?->id ?? $v->id, $so);

            if ($sairam && $ordem) {
                $modelo = __(self::MODELOS[$chave]['name'] ?? $chave);
                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                    __('Aviso «:aviso» enviado ao cliente por :canais.', ['aviso' => $modelo, 'canais' => implode(' e ', $sairam)]));
            }

            return $sairam;
        } catch (\Throwable $e) {
            Log::warning('Aviso da oficina não enviado', ['ordem' => $ordem?->id, 'viatura' => $v->id, 'aviso' => $chave, 'erro' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * O ENVIO COMUM — cliente ou dono: o modelo activo da empresa, pelos
     * canais que ela ligou e que o modelo tem.
     *
     * @param  list<string>|null  $so  só estes canais ('sms', 'email'); nulo = os dois
     * @return list<string> os canais por onde saiu
     */
    private static function enviarModelo(int $tenantId, string $chave, ?string $telefone, ?string $email, array $variaveis, int $registo, ?array $so = null): array
    {
        if (! self::activos($tenantId)) {
            return [];
        }

        self::garantirModelos($tenantId);

        $modelo = NotificationTemplate::withoutGlobalScopes()->where('slug', "oficina-{$chave}-{$tenantId}")->where('is_active', true)->first();
        if (! $modelo) {
            return [];
        }

        $envio = app(EnvioDeNotificacoes::class);
        $sairam = [];
        // Só pelos canais que a empresa ligou: o modelo pode ter os dois e a empresa só o SMS.
        $d = TenantNotificationSetting::getForTenant($tenantId);
        $comSms = (bool) ($d->sms_enabled ?? false) && ($so === null || in_array('sms', $so, true));
        $comEmail = ($d->email_enabled ?? false) && filled($d->smtp_host ?? null) && ($so === null || in_array('email', $so, true));

        if ($comSms && $modelo->sms_enabled && filled($telefone) && $envio->enviar($modelo, 'sms', (string) $telefone, $variaveis, $registo)) {
            $sairam[] = 'SMS';
        }
        if ($comEmail && $modelo->email_enabled && filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && $envio->enviar($modelo, 'email', (string) $email, $variaveis, $registo)) {
            $sairam[] = 'email';
        }

        return $sairam;
    }

    /** @return array{0: ?string, 1: ?string} o telefone e o email de quem se avisa */
    public static function contactos(Vehicle $v): array
    {
        $cliente = $v->client_id ? \App\Models\Client::withoutGlobalScopes()->find($v->client_id) : null;

        return [
            $v->owner_phone ?: ($cliente?->phone ?: $cliente?->mobile),
            $v->owner_email ?: $cliente?->email,
        ];
    }

    public static function variaveis(Vehicle $v): array
    {
        $cliente = $v->client_id ? \App\Models\Client::withoutGlobalScopes()->find($v->client_id) : null;

        return [
            'cliente' => $v->owner_name ?: $cliente?->name,
            'matricula' => $v->plate,
            'viatura' => trim(($v->brand ?? '') . ' ' . ($v->model ?? '')),
            'empresa' => Tenant::find($v->tenant_id)?->name,
        ];
    }

    /**
     * O texto curto do aviso, já preenchido — o do modelo da empresa se o tem,
     * senão o de origem. É o que segue no WhatsApp (que sai do telefone de quem
     * clica, sem o módulo Notificações).
     */
    public static function texto(Vehicle $v, string $chave, array $extra = []): string
    {
        $modelo = NotificationTemplate::where('tenant_id', $v->tenant_id)->where('slug', "oficina-{$chave}-{$v->tenant_id}")->first();
        $texto = $modelo?->sms_body ?: __(self::MODELOS[$chave]['sms_body'] ?? '');
        $variaveis = self::variaveis($v) + $extra;

        return (string) preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($variaveis[$m[1]] ?? ''), $texto);
    }
}
