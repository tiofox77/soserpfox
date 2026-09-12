<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppSetting;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * O WHATSAPP DA PLATAFORMA (Twilio) — as credenciais, os modelos e os avisos.
 *
 * O DEFEITO: O TOKEN DA TWILIO VIAJAVA PARA A PÁGINA. O componente punha-o
 * numa propriedade pública ao abrir — e uma propriedade pública do Livewire vai
 * dentro do HTML, legível por quem abrir o código-fonte ou por uma extensão do
 * browser. Com esse token manda-se mensagens em nome da plataforma. Agora diz-se
 * só se está guardado, e o campo vazio é «manter o que está».
 *
 * E GRAVAR COM O CAMPO VAZIO já não apaga o token: o componente gravava tudo o
 * que estivesse no formulário, e quem o limpasse ficava sem WhatsApp.
 */
class WhatsAppApiController extends Controller
{
    public const AVISOS = [
        'salary_advance_approved' => 'Adiantamento de salário aprovado',
        'salary_advance_rejected' => 'Adiantamento de salário recusado',
        'vacation_approved' => 'Férias aprovadas',
        'vacation_rejected' => 'Férias recusadas',
        'payslip_ready' => 'Recibo de vencimento disponível',
        'employee_created' => 'Funcionário criado',
    ];

    public function index(): JsonResponse
    {
        $s = WhatsAppSetting::getSettings();
        $avisos = $s->notification_settings ?? [];

        return response()->json([
            'configuracao' => [
                'twilio_account_sid' => $s->twilio_account_sid ?? '',
                'twilio_auth_token' => '',
                'token_guardado' => filled($s->twilio_auth_token),
                'whatsapp_from_number' => $s->whatsapp_from_number ?? '',
                'whatsapp_business_account_id' => $s->whatsapp_business_account_id ?? '',
                'is_enabled' => (bool) $s->is_enabled,
                'is_sandbox' => (bool) $s->is_sandbox,
                'templates' => array_values($s->templates ?? []),
                'notification_settings' => collect(self::AVISOS)->mapWithKeys(fn ($r, $k) => [$k => (bool) ($avisos[$k] ?? false)]),
            ],
            'avisos' => collect(self::AVISOS)->map(fn ($r, $k) => ['valor' => $k, 'rotulo' => __($r)])->values(),
            'activo' => $s->isActive(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'twilio_account_sid' => ['nullable', 'string', 'max:100'],
            'twilio_auth_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_from_number' => ['nullable', 'string', 'max:40'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['boolean'],
            'is_sandbox' => ['boolean'],
            'templates' => ['array'],
            'templates.*.sid' => ['required', 'string', 'max:100'],
            'templates.*.name' => ['nullable', 'string', 'max:255'],
            'templates.*.language' => ['nullable', 'string', 'max:20'],
            'notification_settings' => ['array'],
            'notification_settings.*' => ['boolean'],
        ]);

        $s = WhatsAppSetting::getSettings();

        // LIGAR SEM CREDENCIAIS não liga nada — diz-se em vez de fingir.
        $sid = $d['twilio_account_sid'] ?? $s->twilio_account_sid;
        $token = filled($d['twilio_auth_token'] ?? null) ? $d['twilio_auth_token'] : $s->twilio_auth_token;

        if (($d['is_enabled'] ?? false) && (blank($sid) || blank($token) || blank($d['whatsapp_from_number'] ?? null))) {
            throw ValidationException::withMessages([
                'is_enabled' => __('Para ligar o WhatsApp é preciso a conta, o token e o número de envio.'),
            ]);
        }

        $campos = [
            'twilio_account_sid' => $d['twilio_account_sid'] ?? null,
            'whatsapp_from_number' => $d['whatsapp_from_number'] ?? null,
            'whatsapp_business_account_id' => $d['whatsapp_business_account_id'] ?? null,
            'is_enabled' => (bool) ($d['is_enabled'] ?? false),
            'is_sandbox' => (bool) ($d['is_sandbox'] ?? true),
            'templates' => array_values($d['templates'] ?? []),
            'notification_settings' => collect(self::AVISOS)
                ->mapWithKeys(fn ($r, $k) => [$k => (bool) ($d['notification_settings'][$k] ?? false)])->all(),
        ];

        if (filled($d['twilio_auth_token'] ?? null)) {
            $campos['twilio_auth_token'] = $d['twilio_auth_token'];
        }

        $s->update($campos);

        return response()->json(['message' => __('Configuração do WhatsApp guardada.')]);
    }

    public function testarLigacao(WhatsAppService $servico): JsonResponse
    {
        $r = $servico->testConnection();

        return response()->json(['sucesso' => (bool) $r['success'], 'message' => $r['message']], $r['success'] ? 200 : 422);
    }

    public function modelosDaTwilio(WhatsAppService $servico): JsonResponse
    {
        $modelos = $servico->fetchTemplates();

        return response()->json([
            'modelos' => array_values($modelos),
            'message' => __(':n modelo(s) encontrados na Twilio.', ['n' => count($modelos)]),
        ]);
    }

    public function enviarTeste(Request $request, WhatsAppService $servico): JsonResponse
    {
        $d = $request->validate([
            'numero' => ['required', 'string', 'max:40'],
            'mensagem' => ['required', 'string', 'max:1600'],
        ], [], [
            'numero' => __('número'),
            'mensagem' => __('mensagem'),
        ]);

        $sid = $servico->sendMessage($d['numero'], $d['mensagem']);

        if (! $sid) {
            throw ValidationException::withMessages(['numero' => __('A mensagem de teste não saiu. Veja o registo do sistema.')]);
        }

        return response()->json(['message' => __('Mensagem de teste enviada (SID :sid).', ['sid' => $sid])]);
    }
}
