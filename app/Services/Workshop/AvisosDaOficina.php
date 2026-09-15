<?php

namespace App\Services\Workshop;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\TenantNotificationSetting;
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
                    'sms_enabled' => true,
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
        $chave = self::chaveDoEstado($estado);

        if ($chave) {
            self::depois(fn () => self::avisar($ordem, $chave));
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
            $tenantId = (int) $v->tenant_id;

            if (! self::activos($tenantId)) {
                return [];
            }

            self::garantirModelos($tenantId);

            $modelo = NotificationTemplate::withoutGlobalScopes()->where('slug', "oficina-{$chave}-{$tenantId}")->where('is_active', true)->first();
            if (! $modelo) {
                return [];
            }

            [$telefone, $email] = self::contactos($v);
            $variaveis = self::variaveis($v) + $extra;
            $registo = $ordem?->id ?? $v->id;

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

            if ($sairam && $ordem) {
                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                    __('Aviso «:aviso» enviado ao cliente por :canais.', ['aviso' => $modelo->name, 'canais' => implode(' e ', $sairam)]));
            }

            return $sairam;
        } catch (\Throwable $e) {
            Log::warning('Aviso da oficina não enviado', ['ordem' => $ordem?->id, 'viatura' => $v->id, 'aviso' => $chave, 'erro' => $e->getMessage()]);

            return [];
        }
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
