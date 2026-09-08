<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\D7NetworksService;
use App\Services\TelcoSmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Configuracao multicanal do tenant, sem nunca expor credenciais gravadas. */
class NotificationGatewaysApiController extends Controller
{
    private const SEGREDOS = ['smtp_password', 'sms_auth_token', 'sms_api_token', 'whatsapp_auth_token'];

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.view');
        $tenantId = $this->tenant();
        $s = TenantNotificationSetting::getForTenant($tenantId);

        $campos = collect($s->getFillable())->reject(fn (string $campo) => in_array($campo, self::SEGREDOS, true));
        $forma = $campos->mapWithKeys(fn (string $campo) => [$campo => $s->{$campo}])->all();
        $forma['email_notifications'] ??= TenantNotificationSetting::getDefaultEmailNotifications();
        $forma['sms_notifications'] ??= TenantNotificationSetting::getDefaultSmsNotifications();
        $forma['whatsapp_notifications'] ??= TenantNotificationSetting::getDefaultWhatsAppNotifications();
        foreach (['whatsapp_templates', 'whatsapp_notification_templates', 'sms_notification_templates', 'email_notification_templates'] as $campo) {
            $forma[$campo] ??= [];
        }

        return response()->json([
            'definicoes' => $forma,
            'segredos_guardados' => collect(self::SEGREDOS)->mapWithKeys(
                fn (string $campo) => [$campo => filled($s->{$campo})]
            ),
            'templates' => NotificationTemplate::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'module', 'email_enabled', 'sms_enabled', 'whatsapp_enabled']),
            'eventos' => $this->eventos(),
            'permissoes' => ['pode_editar' => (bool) $request->user()?->can('invoicing.settings.edit')],
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');
        $s = TenantNotificationSetting::getForTenant($this->tenant());
        $dados = $request->validate($this->regras());

        if (($dados['sms_enabled'] ?? false)
            && in_array($dados['sms_provider'] ?? '', ['d7networks', 'telcosms'], true)
            && blank(trim((string) ($dados['sms_api_token'] ?? '')) ?: $s->sms_api_token)) {
            return response()->json([
                'message' => __('Indique a chave da aplicação/API.'),
                'errors' => ['sms_api_token' => [__('Indique a chave da aplicação/API antes de ativar este fornecedor.')]],
            ], 422);
        }

        foreach (self::SEGREDOS as $campo) {
            if (blank(trim((string) ($dados[$campo] ?? '')))) {
                unset($dados[$campo]); // vazio significa manter o segredo actual
            }
        }

        if (($dados['sms_provider'] ?? $s->sms_provider) === 'telcosms') {
            $dados['sms_sender_id'] = 'SOSERP';
        }

        $s->update($dados);

        return response()->json([
            'message' => __('Configurações de notificações guardadas com sucesso.'),
            'segredos_guardados' => collect(self::SEGREDOS)->mapWithKeys(
                fn (string $campo) => [$campo => filled($s->fresh()->{$campo})]
            ),
        ]);
    }

    /** Consulta real: o ecrã identifica claramente que contacta o fornecedor. */
    public function testarSms(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');
        $dados = $request->validate([
            'sms_provider' => ['required', 'in:telcosms,d7networks,twilio,nexmo,other'],
            'sms_api_token' => ['nullable', 'string', 'max:4096'],
            'sms_sender_id' => ['nullable', 'string', 'max:30'],
        ]);
        $s = TenantNotificationSetting::getForTenant($this->tenant());
        $token = trim((string) ($dados['sms_api_token'] ?? '')) ?: $s->sms_api_token;

        if (blank($token)) {
            return response()->json(['message' => __('Não existe uma chave API guardada.'), 'errors' => ['sms_api_token' => [__('Escreva ou guarde a chave antes de testar.')]]], 422);
        }

        $resultado = match ($dados['sms_provider']) {
            'telcosms' => (new TelcoSmsService($token))->checkBalance(),
            'd7networks' => (new D7NetworksService($token, $dados['sms_sender_id'] ?? $s->sms_sender_id))->testConnection(),
            default => ['success' => false, 'message' => __('Este fornecedor ainda não oferece consulta automática.')],
        };

        return response()->json($resultado, ($resultado['success'] ?? false) ? 200 : 422);
    }

    public function testarEmail(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');
        $dados = $request->validate([
            'smtp_host' => ['required', 'string', 'max:255'], 'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'], 'smtp_password' => ['nullable', 'string', 'max:4096'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl'], 'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);
        $s = TenantNotificationSetting::getForTenant($this->tenant());
        $senha = trim((string) ($dados['smtp_password'] ?? '')) ?: $s->smtp_password;
        abort_if(blank($senha), 422, __('Não existe uma senha SMTP guardada.'));

        $porta = (int) $dados['smtp_port'];
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => [
            'transport' => 'smtp', 'host' => $dados['smtp_host'], 'port' => $porta,
            'encryption' => $dados['smtp_encryption'] ?? ($porta === 465 ? 'ssl' : 'tls'),
            'username' => $dados['smtp_username'] ?? null, 'password' => $senha, 'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url'), PHP_URL_HOST)),
            'verify_peer' => false,
        ], 'mail.from.address' => $dados['from_email'], 'mail.from.name' => $dados['from_name'] ?: config('app.name')]);
        app('mail.manager')->purge('smtp');

        Mail::raw(__('Esta mensagem confirma que o gateway SMTP da sua empresa está operacional.'), function ($mensagem) use ($dados) {
            $mensagem->to($dados['from_email'])->from($dados['from_email'], $dados['from_name'] ?: config('app.name'))
                ->subject('[TESTE] ' . config('app.name'));
        });

        return response()->json(['success' => true, 'message' => __('Email de teste enviado para :email.', ['email' => $dados['from_email']])]);
    }

    public function templatesWhatsApp(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');
        [$dados, $token] = $this->credenciaisWhatsApp($request, false);
        $templates = (new WhatsAppService($dados['whatsapp_account_sid'], $token, $dados['whatsapp_from_number'] ?? null))->fetchTemplates();
        return response()->json(['templates' => $templates, 'message' => trans_choice(':count template encontrado.|:count templates encontrados.', count($templates), ['count' => count($templates)])]);
    }

    public function testarWhatsApp(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');
        [$dados, $token] = $this->credenciaisWhatsApp($request, true);
        $servico = new WhatsAppService($dados['whatsapp_account_sid'], $token, $dados['whatsapp_from_number']);
        $sid = $servico->sendTemplate($dados['test_phone'], $dados['template_name'], $dados['variables'] ?? [], $dados['template_sid']);
        abort_unless($sid, 422, __('O fornecedor não confirmou o envio.'));
        return response()->json(['success' => true, 'message' => __('Mensagem de teste enviada.'), 'sid' => $sid]);
    }

    private function credenciaisWhatsApp(Request $request, bool $paraEnvio): array
    {
        $regras = [
            'whatsapp_account_sid' => ['required', 'string', 'max:255'], 'whatsapp_auth_token' => ['nullable', 'string', 'max:4096'],
            'whatsapp_from_number' => [$paraEnvio ? 'required' : 'nullable', 'string', 'max:30'],
        ];
        if ($paraEnvio) $regras += [
            'test_phone' => ['required', 'string', 'max:30'], 'template_sid' => ['required', 'string', 'max:255'],
            'template_name' => ['required', 'string', 'max:255'], 'variables' => ['sometimes', 'array'],
        ];
        $dados = $request->validate($regras);
        $s = TenantNotificationSetting::getForTenant($this->tenant());
        $token = trim((string) ($dados['whatsapp_auth_token'] ?? '')) ?: $s->whatsapp_auth_token;
        abort_if(blank($token), 422, __('Não existe um token WhatsApp guardado.'));
        return [$dados, $token];
    }

    private function regras(): array
    {
        $booleanos = ['email_enabled', 'sms_enabled', 'whatsapp_enabled', 'whatsapp_sandbox'];
        $arrays = ['email_notifications', 'sms_notifications', 'whatsapp_notifications', 'whatsapp_templates',
            'whatsapp_notification_templates', 'sms_notification_templates', 'email_notification_templates'];
        $regras = [];
        foreach ($booleanos as $campo) $regras[$campo] = ['required', 'boolean'];
        foreach ($arrays as $campo) $regras[$campo] = ['sometimes', 'array'];

        return $regras + [
            'smtp_host' => ['exclude_unless:email_enabled,true', 'required', 'string', 'max:255'],
            'smtp_port' => ['exclude_unless:email_enabled,true', 'required', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:4096'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl'],
            'from_email' => ['exclude_unless:email_enabled,true', 'required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'sms_provider' => ['exclude_unless:sms_enabled,true', 'required', 'in:twilio,telcosms,d7networks,nexmo,other'],
            'sms_account_sid' => ['nullable', 'string', 'max:255'],
            'sms_auth_token' => ['nullable', 'string', 'max:4096'],
            'sms_from_number' => ['nullable', 'string', 'max:30'],
            'sms_api_token' => ['nullable', 'string', 'max:4096'],
            'sms_sender_id' => ['nullable', 'string', 'max:30'],
            'whatsapp_provider' => ['exclude_unless:whatsapp_enabled,true', 'required', 'in:twilio,meta'],
            'whatsapp_account_sid' => ['nullable', 'string', 'max:255'],
            'whatsapp_auth_token' => ['nullable', 'string', 'max:4096'],
            'whatsapp_from_number' => ['exclude_unless:whatsapp_enabled,true', 'required', 'string', 'max:30'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function eventos(): array
    {
        return [
            ['chave' => 'employee_created', 'nome' => __('Funcionário criado'), 'icone' => 'fa-user-plus'],
            ['chave' => 'advance_approved', 'nome' => __('Adiantamento aprovado'), 'icone' => 'fa-circle-check'],
            ['chave' => 'advance_rejected', 'nome' => __('Adiantamento rejeitado'), 'icone' => 'fa-circle-xmark'],
            ['chave' => 'leave_approved', 'nome' => __('Férias aprovadas'), 'icone' => 'fa-umbrella-beach'],
            ['chave' => 'leave_rejected', 'nome' => __('Férias rejeitadas'), 'icone' => 'fa-ban'],
            ['chave' => 'payslip_ready', 'nome' => __('Recibo de pagamento'), 'icone' => 'fa-file-invoice'],
            ['chave' => 'event_created', 'nome' => __('Evento criado'), 'icone' => 'fa-calendar-plus'],
            ['chave' => 'event_reminder', 'nome' => __('Lembrete de evento'), 'icone' => 'fa-bell'],
            ['chave' => 'technician_assigned', 'nome' => __('Técnico designado'), 'icone' => 'fa-user-tag'],
            ['chave' => 'event_cancelled', 'nome' => __('Evento cancelado'), 'icone' => 'fa-calendar-xmark'],
            ['chave' => 'task_assigned', 'nome' => __('Tarefa atribuída'), 'icone' => 'fa-list-check'],
            ['chave' => 'meeting_scheduled', 'nome' => __('Reunião agendada'), 'icone' => 'fa-handshake'],
        ];
    }

    private function tenant(): int
    {
        $tenantId = activeTenantId();
        abort_unless($tenantId, 409, __('Nenhuma empresa activa.'));
        return (int) $tenantId;
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
