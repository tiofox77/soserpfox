<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehicleReminder;
use App\Models\Workshop\WorkshopSetting;
use App\Services\Workshop\AvisosDaOficina;
use App\Services\Workshop\LembretesDaOficina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * OS LEMBRETES DE MANUTENÇÃO (15/09/2026, OF-11).
 *
 * Ver a lista pede ver viaturas; contactar, adiar, mudar a revisão e as
 * definições pedem editar viaturas. O SMS e o email só saem com o módulo
 * Notificações e o canal configurados; WhatsApp e telefone saem do aparelho de
 * quem clica e aqui só se regista que se fez.
 */
class LembretesDaOficinaApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function viatura(int $id): Vehicle
    {
        return Vehicle::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.view');
        $tenantId = activeTenantId();
        $d = WorkshopSetting::getForTenant($tenantId);
        $itens = LembretesDaOficina::devidos($tenantId);
        $notificacoes = \App\Models\TenantNotificationSetting::getForTenant($tenantId);
        $activos = AvisosDaOficina::activos($tenantId);

        return response()->json([
            'data' => $itens,
            'definicoes' => self::definicoesParaEcra($d),
            'canais' => [
                'sms' => $activos && (bool) $notificacoes->sms_enabled,
                'email' => $activos && $notificacoes->email_enabled && filled($notificacoes->smtp_host),
            ],
            'tipos' => collect(VehicleReminder::TIPOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'pode_gerir' => (bool) $request->user()?->can('workshop.vehicles.edit'),
        ]);
    }

    public function definicoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $dados = $request->validate([
            'service_interval_km' => ['required', 'integer', 'min:0', 'max:200000'],
            'service_interval_months' => ['required', 'integer', 'min:0', 'max:60'],
            'remind_days_before' => ['required', 'integer', 'min:0', 'max:180'],
            'remind_km_before' => ['required', 'integer', 'min:0', 'max:20000'],
            'documents_days_before' => ['required', 'integer', 'min:0', 'max:180'],
            'auto_reminders' => ['required', 'boolean'],
        ]);

        $d = WorkshopSetting::getForTenant(activeTenantId());
        $d->update($dados);

        return response()->json(['definicoes' => self::definicoesParaEcra($d->fresh()), 'message' => __('Definições dos lembretes guardadas.')]);
    }

    /** Um contacto: SMS/email saem daqui; WhatsApp, telefone e nota só se registam. */
    public function contacto(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $v = $this->viatura($id);
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(VehicleReminder::TIPOS))],
            'canal' => ['required', Rule::in(array_keys(VehicleReminder::CANAIS))],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);

        $vencimento = LembretesDaOficina::vencimento($v, $dados['tipo']);
        abort_unless($vencimento, 422, __('Esta viatura não tem esse vencimento marcado.'));

        if (in_array($dados['canal'], ['sms', 'email'], true)) {
            $sairam = AvisosDaOficina::avisarViatura($v, $dados['tipo'], LembretesDaOficina::variaveis($v, $dados['tipo']), null, [$dados['canal']]);

            if (! $sairam) {
                return response()->json(['message' => $dados['canal'] === 'sms'
                    ? __('O SMS não saiu: confirme o telefone do dono e o SMS em Notificações.')
                    : __('O email não saiu: confirme o email do dono e o SMTP em Notificações.')], 422);
            }
        }

        LembretesDaOficina::registar($v, $dados['tipo'], $vencimento, $dados['canal'], $dados['nota'] ?? null, $request->user()?->id);

        return response()->json(['message' => match ($dados['canal']) {
            'sms' => __('Lembrete enviado por SMS a :m.', ['m' => $v->plate]),
            'email' => __('Lembrete enviado por email a :m.', ['m' => $v->plate]),
            default => __('Contacto registado em :m.', ['m' => $v->plate]),
        }]);
    }

    /** Adiar os lembretes da viatura (0 dias = retomar). */
    public function adiar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $v = $this->viatura($id);
        $dias = (int) $request->validate(['dias' => ['required', 'integer', 'min:0', 'max:365']])['dias'];

        $v->update(['reminders_paused_until' => $dias ? today()->addDays($dias) : null]);

        return response()->json(['message' => $dias
            ? __('Lembretes de :m adiados até :data.', ['m' => $v->plate, 'data' => today()->addDays($dias)->format('d/m/Y')])
            : __('Lembretes de :m retomados.', ['m' => $v->plate])]);
    }

    /** A próxima revisão escrita à mão (e o intervalo desta viatura). */
    public function revisao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $v = $this->viatura($id);
        $dados = $request->validate([
            'proxima_data' => ['nullable', 'date'],
            'proximo_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'intervalo_km' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'intervalo_meses' => ['nullable', 'integer', 'min:0', 'max:60'],
            'feita' => ['sometimes', 'boolean'],
        ]);

        $mudar = [
            'next_service_date' => $dados['proxima_data'] ?? null,
            'next_service_km' => $dados['proximo_km'] ?? null,
            'service_interval_km' => $dados['intervalo_km'] ?? null,
            'service_interval_months' => $dados['intervalo_meses'] ?? null,
            'reminders_paused_until' => null,
        ];

        // «Revisão feita noutro lado»: hoje, com os km de agora, e a próxima pelo intervalo.
        if ($request->boolean('feita')) {
            $d = WorkshopSetting::getForTenant((int) $v->tenant_id);
            $v->fill($mudar);
            [$porKm, $porMeses] = LembretesDaOficina::intervalos($v, $d);
            $km = (int) $v->mileage;
            $mudar += ['last_service_date' => today(), 'last_service_km' => $km ?: null];
            $mudar['next_service_km'] = $porKm && $km ? $km + $porKm : null;
            $mudar['next_service_date'] = $porMeses ? today()->addMonthsNoOverflow($porMeses) : null;
        }

        $v->update($mudar);
        $v->refresh();

        return response()->json([
            'message' => $v->next_service_date || $v->next_service_km
                ? __('Próxima revisão de :m :quando.', ['m' => $v->plate, 'quando' => LembretesDaOficina::quando($v->next_service_km, $v->next_service_date)])
                : __('Revisão de :m sem data nem km.', ['m' => $v->plate]),
        ]);
    }

    private static function definicoesParaEcra(WorkshopSetting $d): array
    {
        return $d->only(['service_interval_km', 'service_interval_months', 'remind_days_before', 'remind_km_before', 'documents_days_before', 'auto_reminders']);
    }
}
