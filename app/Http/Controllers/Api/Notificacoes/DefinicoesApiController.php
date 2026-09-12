<?php

namespace App\Http\Controllers\Api\Notificacoes;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\D7NetworksService;
use App\Services\TelcoSmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS DEFINIÇÕES DE NOTIFICAÇÃO: e-mail, SMS e WhatsApp.
 *
 * OS SEGREDOS NÃO SAEM DAQUI. A senha do SMTP e os tokens das operadoras estão
 * cifrados em repouso e nunca viajam para o browser — o ecrã limita-se a dizer
 * que existe um guardado. Os campos abrem VAZIOS, e um campo vazio ao gravar
 * quer dizer «mantém o que lá está», nunca «apaga».
 *
 * VER NÃO É CONFIGURAR. A página estava atrás de `notifications.view` — a mais
 * fraca das três permissões do módulo — e quem a abrisse mudava o servidor de
 * saída da empresa. `notifications.manage` («Gerir Configurações de
 * Notificações») estava declarada há muito e nunca ninguém a pediu.
 */
class DefinicoesApiController extends Controller
{
    /** Os campos que são segredo, e que por isso só se gravam quando escritos. */
    private const SEGREDOS = [
        'smtp_password',
        'sms_auth_token',
        'sms_api_token',
        'whatsapp_auth_token',
    ];

    private function empresaId(): int
    {
        return (int) activeTenantId();
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    private function definicoes(): TenantNotificationSetting
    {
        return TenantNotificationSetting::getForTenant($this->empresaId());
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        $d = $this->definicoes();

        $modelos = NotificationTemplate::where('tenant_id', $this->empresaId())
            ->orderBy('name')->get();

        return response()->json([
            'data' => [
                'email' => [
                    'enabled' => (bool) $d->email_enabled,
                    'smtp_host' => $d->smtp_host,
                    'smtp_port' => $d->smtp_port ?? 587,
                    'smtp_username' => $d->smtp_username,
                    'smtp_encryption' => $d->smtp_encryption ?? 'tls',
                    'from_email' => $d->from_email,
                    'from_name' => $d->from_name,
                    'notifications' => $d->email_notifications ?? TenantNotificationSetting::getDefaultEmailNotifications(),
                    'notification_templates' => (object) ($d->email_notification_templates ?? []),
                ],
                'sms' => [
                    'enabled' => (bool) $d->sms_enabled,
                    'provider' => $d->sms_provider ?? '',
                    'account_sid' => $d->sms_account_sid,
                    'from_number' => $d->sms_from_number,
                    'sender_id' => $d->sms_sender_id,
                    'notifications' => $d->sms_notifications ?? TenantNotificationSetting::getDefaultSmsNotifications(),
                    'notification_templates' => (object) ($d->sms_notification_templates ?? []),
                ],
                'whatsapp' => [
                    'enabled' => (bool) $d->whatsapp_enabled,
                    'provider' => $d->whatsapp_provider ?? 'twilio',
                    'account_sid' => $d->whatsapp_account_sid,
                    'from_number' => $d->whatsapp_from_number,
                    'business_account_id' => $d->whatsapp_business_account_id,
                    'sandbox' => $d->whatsapp_sandbox === null ? true : (bool) $d->whatsapp_sandbox,
                    'notifications' => $d->whatsapp_notifications ?? TenantNotificationSetting::getDefaultWhatsAppNotifications(),
                    'notification_templates' => (object) ($d->whatsapp_notification_templates ?? []),
                    'templates' => $d->whatsapp_templates ?? [],
                ],
            ],
            /*
             * O QUE HÁ, SEM DIZER O QUE É.
             *
             * O ecrã precisa de saber se já existe uma senha guardada — para
             * mostrar «configurado» e para não obrigar a reescrevê-la — mas não
             * pode receber o valor. Um `type="password"` esconde os caracteres
             * no ecrã, não no código-fonte da página.
             */
            'segredos' => collect(self::SEGREDOS)->mapWithKeys(
                fn ($campo) => [$campo => filled($d->{$campo})],
            ),
            'tipos' => $this->tiposDeAviso(),
            'modelos' => $modelos->map(fn (NotificationTemplate $m) => [
                'id' => $m->id,
                'nome' => $m->name,
                'modulo' => $m->module,
                'email' => (bool) $m->email_enabled,
                'sms' => (bool) $m->sms_enabled,
                'whatsapp' => (bool) $m->whatsapp_enabled,
            ])->values(),
            'operadoras' => [
                'sms' => [
                    ['valor' => 'telcosms', 'rotulo' => 'TelcoSMS', 'chave' => 'sms_api_token'],
                    ['valor' => 'd7networks', 'rotulo' => 'D7 Networks', 'chave' => 'sms_api_token'],
                    ['valor' => 'twilio', 'rotulo' => 'Twilio', 'chave' => 'sms_auth_token'],
                ],
                'whatsapp' => [
                    ['valor' => 'twilio', 'rotulo' => 'Twilio'],
                ],
            ],
            'permissoes' => [
                'configurar' => (bool) $request->user()?->can('notifications.manage'),
                'testar' => (bool) ($request->user()?->can('notifications.send')
                    || $request->user()?->can('notifications.manage')),
            ],
        ]);
    }

    /** Os avisos que o sistema sabe mandar, em português. */
    private function tiposDeAviso(): array
    {
        return [
            ['valor' => 'employee_created', 'rotulo' => __('Funcionário admitido'), 'grupo' => __('Recursos Humanos')],
            ['valor' => 'advance_approved', 'rotulo' => __('Adiantamento aprovado'), 'grupo' => __('Recursos Humanos')],
            ['valor' => 'advance_rejected', 'rotulo' => __('Adiantamento recusado'), 'grupo' => __('Recursos Humanos')],
            ['valor' => 'leave_approved', 'rotulo' => __('Licença aprovada'), 'grupo' => __('Recursos Humanos')],
            ['valor' => 'leave_rejected', 'rotulo' => __('Licença recusada'), 'grupo' => __('Recursos Humanos')],
            ['valor' => 'payslip_ready', 'rotulo' => __('Recibo de vencimento pronto'), 'grupo' => __('Recursos Humanos')],
            ['valor' => 'event_created', 'rotulo' => __('Evento criado'), 'grupo' => __('Eventos')],
            ['valor' => 'event_reminder', 'rotulo' => __('Lembrete de evento'), 'grupo' => __('Eventos')],
            ['valor' => 'event_cancelled', 'rotulo' => __('Evento cancelado'), 'grupo' => __('Eventos')],
            ['valor' => 'technician_assigned', 'rotulo' => __('Técnico destacado'), 'grupo' => __('Eventos')],
            ['valor' => 'task_assigned', 'rotulo' => __('Tarefa atribuída'), 'grupo' => __('Trabalho')],
            ['valor' => 'meeting_scheduled', 'rotulo' => __('Reunião marcada'), 'grupo' => __('Trabalho')],
        ];
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'notifications.manage');

        /*
         * AS REGRAS DE UM CANAL SÓ VALEM COM ELE LIGADO.
         *
         * Quem tem o SMS desligado não tem de preencher nada dele para poder
         * gravar o resto. E com o canal ligado, um host vazio ou um e-mail
         * inválido são recusados AGORA — antes só se descobria na primeira
         * tentativa de envio, sem ninguém ligar uma coisa à outra.
         */
        $dados = $request->validate([
            'email.enabled' => ['boolean'],
            'email.smtp_host' => ['exclude_unless:email.enabled,true', 'required', 'string', 'max:255'],
            'email.smtp_port' => ['exclude_unless:email.enabled,true', 'required', 'integer', 'min:1', 'max:65535'],
            'email.smtp_username' => ['nullable', 'string', 'max:255'],
            'email.smtp_password' => ['nullable', 'string', 'max:255'],
            'email.smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'email.from_email' => ['exclude_unless:email.enabled,true', 'required', 'email', 'max:255'],
            'email.from_name' => ['nullable', 'string', 'max:255'],
            'email.notifications' => ['nullable', 'array'],
            'email.notification_templates' => ['nullable', 'array'],

            'sms.enabled' => ['boolean'],
            'sms.provider' => ['exclude_unless:sms.enabled,true', 'required', 'string', 'max:50'],
            'sms.account_sid' => ['nullable', 'string', 'max:255'],
            'sms.auth_token' => ['nullable', 'string', 'max:255'],
            'sms.api_token' => ['nullable', 'string', 'max:255'],
            'sms.from_number' => ['nullable', 'string', 'max:30'],
            'sms.sender_id' => ['nullable', 'string', 'max:30'],
            'sms.notifications' => ['nullable', 'array'],
            'sms.notification_templates' => ['nullable', 'array'],

            'whatsapp.enabled' => ['boolean'],
            'whatsapp.provider' => ['exclude_unless:whatsapp.enabled,true', 'required', 'string', 'max:50'],
            'whatsapp.account_sid' => ['nullable', 'string', 'max:255'],
            'whatsapp.auth_token' => ['nullable', 'string', 'max:255'],
            'whatsapp.from_number' => ['exclude_unless:whatsapp.enabled,true', 'required', 'string', 'max:30'],
            'whatsapp.business_account_id' => ['nullable', 'string', 'max:255'],
            'whatsapp.sandbox' => ['boolean'],
            'whatsapp.notifications' => ['nullable', 'array'],
            'whatsapp.notification_templates' => ['nullable', 'array'],
            'whatsapp.templates' => ['nullable', 'array'],
        ], [
            'email.smtp_host.required' => __('Indique o servidor de saída (SMTP) — sem ele não sai e-mail nenhum.'),
            'email.smtp_port.required' => __('Indique a porta do servidor de saída.'),
            'email.smtp_port.integer' => __('A porta tem de ser um número (normalmente 587 ou 465).'),
            'email.from_email.required' => __('Indique o endereço remetente.'),
            'email.from_email.email' => __('O endereço remetente não é um e-mail válido.'),
            'sms.provider.required' => __('Escolha a operadora de SMS.'),
            'whatsapp.provider.required' => __('Escolha o fornecedor de WhatsApp.'),
            'whatsapp.from_number.required' => __('Indique o número de origem do WhatsApp.'),
        ]);

        $d = $this->definicoes();
        $email = $dados['email'] ?? [];
        $sms = $dados['sms'] ?? [];
        $whatsapp = $dados['whatsapp'] ?? [];

        /*
         * UMA OPERADORA LIGADA SEM CHAVE NÃO MANDA NADA.
         *
         * Ligar a TelcoSMS ou a D7 sem chave era ficar com o canal «activo» e
         * nenhum SMS a sair — e ninguém a perceber porquê.
         */
        if (($sms['enabled'] ?? false)
            && in_array($sms['provider'] ?? '', ['d7networks', 'telcosms'], true)
            && blank($sms['api_token'] ?? null)
            && blank($d->sms_api_token)) {
            throw ValidationException::withMessages([
                'sms.api_token' => [__('Indique a chave da aplicação/API antes de activar este fornecedor.')],
            ]);
        }

        $valores = [
            'email_enabled' => (bool) ($email['enabled'] ?? false),
            'smtp_host' => $email['smtp_host'] ?? null,
            'smtp_port' => $email['smtp_port'] ?? null,
            'smtp_username' => $email['smtp_username'] ?? null,
            'smtp_encryption' => $email['smtp_encryption'] ?? null,
            'from_email' => $email['from_email'] ?? null,
            'from_name' => $email['from_name'] ?? null,
            'email_notifications' => $email['notifications'] ?? $d->email_notifications,
            'email_notification_templates' => $email['notification_templates'] ?? $d->email_notification_templates,

            'sms_enabled' => (bool) ($sms['enabled'] ?? false),
            'sms_provider' => $sms['provider'] ?? null,
            'sms_account_sid' => $sms['account_sid'] ?? null,
            'sms_from_number' => $sms['from_number'] ?? null,
            // A TelcoSMS não deixa escolher o remetente: é sempre o registado.
            'sms_sender_id' => ($sms['provider'] ?? null) === 'telcosms' ? 'SOSERP' : ($sms['sender_id'] ?? null),
            'sms_notifications' => $sms['notifications'] ?? $d->sms_notifications,
            'sms_notification_templates' => $sms['notification_templates'] ?? $d->sms_notification_templates,

            'whatsapp_enabled' => (bool) ($whatsapp['enabled'] ?? false),
            'whatsapp_provider' => $whatsapp['provider'] ?? 'twilio',
            'whatsapp_account_sid' => $whatsapp['account_sid'] ?? null,
            'whatsapp_from_number' => $whatsapp['from_number'] ?? null,
            'whatsapp_business_account_id' => $whatsapp['business_account_id'] ?? null,
            'whatsapp_sandbox' => (bool) ($whatsapp['sandbox'] ?? true),
            'whatsapp_notifications' => $whatsapp['notifications'] ?? $d->whatsapp_notifications,
            'whatsapp_notification_templates' => $whatsapp['notification_templates'] ?? $d->whatsapp_notification_templates,
            'whatsapp_templates' => $whatsapp['templates'] ?? $d->whatsapp_templates,
        ];

        /*
         * UM SEGREDO SÓ SE GRAVA QUANDO ALGUÉM ESCREVE UM NOVO.
         *
         * Os campos abrem vazios de propósito. Gravar o vazio apagava a senha
         * do SMTP de quem só veio mudar o nome do remetente.
         */
        $valores += $this->segredo('smtp_password', $email['smtp_password'] ?? null);
        $valores += $this->segredo('sms_auth_token', $sms['auth_token'] ?? null);
        $valores += $this->segredo('sms_api_token', $sms['api_token'] ?? null);
        $valores += $this->segredo('whatsapp_auth_token', $whatsapp['auth_token'] ?? null);

        $d->update($valores);

        return response()->json([
            'message' => __('Definições de notificação guardadas.'),
            'segredos' => collect(self::SEGREDOS)->mapWithKeys(
                fn ($campo) => [$campo => filled($d->fresh()->{$campo})],
            ),
        ]);
    }

    /** @return array<string,string> vazio quando não veio nada escrito */
    private function segredo(string $campo, ?string $valor): array
    {
        $novo = trim((string) $valor);

        return $novo === '' ? [] : [$campo => $novo];
    }

    /**
     * O segredo a usar num teste: o que se acabou de escrever ou, se o campo
     * veio vazio, o que já está guardado.
     *
     * Sem isto, testar a ligação depois de recarregar a página falhava sempre
     * com «senha em falta» — porque o campo abre vazio de propósito.
     */
    private function segredoEfectivo(string $campo, ?string $escrito): ?string
    {
        $valor = trim((string) $escrito);

        return $valor !== '' ? $valor : $this->definicoes()->{$campo};
    }

    /* ─── Os testes ───────────────────────────────────────────────────── */

    private function podeTestar(Request $request): void
    {
        abort_unless(
            $request->user()?->can('notifications.send') || $request->user()?->can('notifications.manage'),
            403,
            __('Sem permissão para enviar testes.'),
        );
    }

    /**
     * O TESTE DO E-MAIL manda um e-mail a sério para o próprio remetente.
     *
     * Uma ligação que abre não prova nada: o que faz falta saber é se a
     * mensagem chega à caixa de entrada.
     */
    public function testarEmail(Request $request): JsonResponse
    {
        $this->podeTestar($request);

        $dados = $request->validate([
            'smtp_host' => ['required', 'string'],
            'smtp_port' => ['required', 'integer'],
            'smtp_username' => ['nullable', 'string'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'from_email' => ['required', 'email'],
            'from_name' => ['nullable', 'string'],
        ]);

        $senha = $this->segredoEfectivo('smtp_password', $dados['smtp_password'] ?? null);

        if (blank($senha)) {
            $this->recusa(__('Escreva a senha do SMTP antes de testar — não há nenhuma guardada.'));
        }

        $porta = (int) $dados['smtp_port'];
        $cifra = $dados['smtp_encryption'] ?: ($porta === 465 ? 'ssl' : 'tls');
        $nome = $dados['from_name'] ?: config('app.name');

        try {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => $dados['smtp_host'],
                    'port' => $porta,
                    'encryption' => $cifra,
                    'username' => $dados['smtp_username'] ?? null,
                    'password' => $senha,
                    'timeout' => 15,
                    'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
                    'verify_peer' => false,
                ],
                'mail.from.address' => $dados['from_email'],
                'mail.from.name' => $nome,
            ]);

            // Sem isto, o Laravel continuava a usar a ligação que já tinha na
            // memória e o teste mediria a configuração ANTERIOR.
            app('mail.manager')->purge('smtp');

            Mail::send([], [], function ($mensagem) use ($dados, $nome, $porta, $cifra) {
                $mensagem->to($dados['from_email'], $nome)
                    ->from($dados['from_email'], $nome)
                    ->replyTo($dados['from_email'], $nome)
                    ->subject('['.__('TESTE').'] '.__('Ligação SMTP').' — '.config('app.name'))
                    ->html($this->corpoDoTeste($dados, $porta, $cifra));
            });

            return response()->json([
                'message' => __('E-mail de teste enviado para :email. Verifique a caixa de entrada.', [
                    'email' => $dados['from_email'],
                ]),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Teste de SMTP falhou', ['host' => $dados['smtp_host'], 'erro' => $e->getMessage()]);

            $this->recusa(__('Erro ao testar o SMTP: :erro', ['erro' => $e->getMessage()]));
        }
    }

    private function corpoDoTeste(array $dados, int $porta, string $cifra): string
    {
        $linha = fn ($rotulo, $valor) => '<tr><td style="padding:8px;border:1px solid #e5e7eb;font-weight:bold;">'
            .e($rotulo).'</td><td style="padding:8px;border:1px solid #e5e7eb;">'.e($valor).'</td></tr>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
            .'<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
            .'<h2 style="color:#4f46e5;">'.e(__('Teste de ligação SMTP')).'</h2>'
            .'<p>'.e(__('Este e-mail confirma que a configuração de saída está a funcionar.')).'</p>'
            .'<table style="width:100%;border-collapse:collapse;margin:15px 0;">'
            .$linha(__('Servidor'), $dados['smtp_host'])
            .$linha(__('Porta'), (string) $porta)
            .$linha(__('Encriptação'), $cifra)
            .$linha(__('Remetente'), $dados['from_email'])
            .$linha(__('Data'), now()->format('d/m/Y H:i:s'))
            .'</table>'
            .'<p style="color:#6b7280;font-size:12px;">'.e(config('app.name')).'</p>'
            .'</div></body></html>';
    }

    public function testarSms(Request $request): JsonResponse
    {
        $this->podeTestar($request);

        $dados = $request->validate([
            'provider' => ['required', 'string'],
            'api_token' => ['nullable', 'string'],
            'auth_token' => ['nullable', 'string'],
            'account_sid' => ['nullable', 'string'],
            'sender_id' => ['nullable', 'string'],
        ]);

        if ($dados['provider'] === 'telcosms') {
            $chave = $this->segredoEfectivo('sms_api_token', $dados['api_token'] ?? null);

            if (blank($chave)) {
                $this->recusa(__('Escreva a chave da aplicação TelcoSMS antes de testar.'));
            }

            $r = (new TelcoSmsService($chave))->checkBalance();

            if (($r['balance_unavailable'] ?? false) === true) {
                return response()->json([
                    'message' => __('A TelcoSMS não deu o saldo agora (HTTP :estado). A chave confirma-se enviando um SMS de teste.', [
                        'estado' => $r['status'] ?? 500,
                    ]),
                    'aviso' => true,
                ]);
            }

            if (! ($r['success'] ?? false)) {
                $this->recusa($r['message'] ?? __('A TelcoSMS recusou a chave.'));
            }

            return response()->json([
                'message' => $r['message'].(isset($r['balance']) && $r['balance'] !== null
                    ? ' '.__('Saldo: :saldo', ['saldo' => $r['balance']]) : ''),
            ]);
        }

        if ($dados['provider'] === 'd7networks') {
            $chave = $this->segredoEfectivo('sms_api_token', $dados['api_token'] ?? null);

            if (blank($chave)) {
                $this->recusa(__('Escreva o token da D7 Networks antes de testar — não há nenhum guardado.'));
            }

            $r = (new D7NetworksService($chave, $dados['sender_id'] ?? null))->testConnection();

            if (! ($r['success'] ?? false)) {
                $this->recusa($r['message'] ?? __('A D7 Networks recusou o token.'));
            }

            return response()->json([
                'message' => $r['message'].' — '.__('Saldo: :saldo :moeda', [
                    'saldo' => $r['balance'] ?? '?', 'moeda' => $r['currency'] ?? '',
                ]),
            ]);
        }

        if ($dados['provider'] === 'twilio') {
            $token = $this->segredoEfectivo('sms_auth_token', $dados['auth_token'] ?? null);

            if (blank($dados['account_sid'] ?? null) || blank($token)) {
                $this->recusa(__('O Twilio precisa do SID da conta e do token.'));
            }

            /*
             * O TWILIO NÃO TEM AQUI UM TESTE A SÉRIO.
             *
             * O ecrã antigo dizia «Configurações do Twilio validadas!» por ter
             * dois campos preenchidos — o que é uma mentira que passa por
             * verificação. Diz-se o que se sabe: os campos estão lá, e a chave
             * confirma-se ao enviar.
             */
            return response()->json([
                'message' => __('Os campos do Twilio estão preenchidos. A chave só se confirma num envio a sério.'),
                'aviso' => true,
            ]);
        }

        $this->recusa(__('A operadora :nome ainda não tem teste de ligação.', ['nome' => $dados['provider']]));
    }

    /** Os modelos de WhatsApp aprovados, buscados ao fornecedor. */
    public function modelosDeWhatsApp(Request $request): JsonResponse
    {
        $this->podeTestar($request);

        $dados = $request->validate([
            'account_sid' => ['required', 'string'],
            'auth_token' => ['nullable', 'string'],
            'from_number' => ['nullable', 'string'],
        ]);

        try {
            $modelos = (new WhatsAppService(
                $dados['account_sid'],
                $this->segredoEfectivo('whatsapp_auth_token', $dados['auth_token'] ?? null),
                $dados['from_number'] ?? null,
            ))->fetchTemplates();

            return response()->json([
                'message' => __(':n modelos encontrados.', ['n' => count($modelos)]),
                'data' => $modelos,
            ]);
        } catch (\Throwable $e) {
            $this->recusa(__('Erro ao buscar os modelos: :erro', ['erro' => $e->getMessage()]));
        }
    }
}
