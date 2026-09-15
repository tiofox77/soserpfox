<?php

namespace App\Services\Workshop;

use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderSurvey;
use Carbon\CarbonInterface as Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * O INQUÉRITO DE SATISFAÇÃO (15/09/2026, OF-14).
 *
 * Nasce quando a ordem passa a Entregue; o link segue por SMS/email (módulo
 * Notificações configurado) e aparece no portal do cliente e na ordem, para a
 * oficina o mandar por WhatsApp. Uma resposta só por ordem.
 */
class InqueritosDaOficina
{
    public static function criar(WorkOrder $ordem): WorkOrderSurvey
    {
        return WorkOrderSurvey::withoutGlobalScope('tenant')->firstOrCreate(
            ['work_order_id' => $ordem->id],
            [
                'tenant_id' => $ordem->tenant_id,
                'vehicle_id' => $ordem->vehicle_id,
                'mechanic_id' => $ordem->mechanic_id,
                'token' => Str::random(48),
            ],
        );
    }

    public static function link(WorkOrderSurvey $s): string
    {
        return route('oficina.avaliar', $s->token);
    }

    /** A ordem foi entregue — o inquérito nasce e o cliente é convidado. */
    public static function entregue(WorkOrder $ordem): void
    {
        try {
            $s = self::criar($ordem);

            if (! $s->answered_at && ! $s->sent_at) {
                AvisosDaOficina::inquerito($ordem, $s, self::link($s));
            }
        } catch (\Throwable $e) {
            Log::warning('Inquérito de satisfação não criado', ['ordem' => $ordem->id, 'erro' => $e->getMessage()]);
        }
    }

    /** A satisfação no período: média, respostas, quem recomenda e os últimos comentários. */
    public static function resumo(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $base = fn () => WorkOrderSurvey::withoutGlobalScope('tenant')->where('tenant_id', $tenantId);
        $respondidos = $base()->whereNotNull('answered_at')->whereBetween('answered_at', [$de, $ate]);
        $n = (clone $respondidos)->count();
        $recomendam = (clone $respondidos)->whereNotNull('would_recommend');
        $comRecomendacao = (clone $recomendam)->count();

        return [
            'media' => $n ? round((float) (clone $respondidos)->avg('score'), 1) : null,
            'respostas' => $n,
            'enviados' => $base()->whereBetween('created_at', [$de, $ate])->count(),
            'recomendam' => $comRecomendacao ? (int) round((clone $recomendam)->where('would_recommend', true)->count() / $comRecomendacao * 100) : null,
            'estrelas' => collect(range(5, 1))->map(fn ($e) => ['estrelas' => $e, 'quantos' => (clone $respondidos)->where('score', $e)->count()])->all(),
            'ultimos' => (clone $respondidos)->with(['workOrder' => fn ($q) => $q->withoutGlobalScopes()->with(['vehicle' => fn ($v) => $v->withoutGlobalScopes()])])
                ->with(['mechanic' => fn ($q) => $q->withoutGlobalScopes()])
                ->orderByDesc('answered_at')->limit(5)->get()
                ->map(fn (WorkOrderSurvey $s) => [
                    'nota' => $s->score,
                    'recomenda' => $s->would_recommend,
                    'comentario' => $s->comment,
                    'quando' => $s->answered_at?->toIso8601String(),
                    'ordem_id' => $s->work_order_id,
                    'ordem' => $s->workOrder?->order_number,
                    'matricula' => $s->workOrder?->vehicle?->plate,
                    'dono' => $s->workOrder?->vehicle?->owner_name,
                    'mecanico' => $s->mechanic?->name,
                ])->all(),
        ];
    }

    /** @return array<int, array{media: float, respostas: int}> por mecânico */
    public static function porMecanico(int $tenantId, Carbon $de, Carbon $ate): array
    {
        return DB::table('workshop_surveys')->where('tenant_id', $tenantId)->whereNotNull('answered_at')->whereNotNull('mechanic_id')
            ->whereBetween('answered_at', [$de, $ate])->groupBy('mechanic_id')
            ->selectRaw('mechanic_id, AVG(score) as media, COUNT(*) as respostas')->get()
            ->mapWithKeys(fn ($l) => [(int) $l->mechanic_id => ['media' => round((float) $l->media, 1), 'respostas' => (int) $l->respostas]])
            ->all();
    }

    public static function paraEcra(?WorkOrderSurvey $s): ?array
    {
        return $s ? [
            'link' => self::link($s),
            'nota' => $s->score,
            'recomenda' => $s->would_recommend,
            'comentario' => $s->comment,
            'respondido_em' => $s->answered_at?->toIso8601String(),
            'enviado_em' => $s->sent_at?->toIso8601String(),
        ] : null;
    }
}
