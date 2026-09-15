<?php

namespace App\Services\Workshop;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * OS INDICADORES DA OFICINA (15/09/2026, OF-18).
 *
 * As contas que os tops põem no topo: ticket médio, taxa de aprovação dos
 * orçamentos, horas vendidas contra trabalhadas, tempo médio na oficina e a
 * margem das peças e da mão-de-obra — num período, e o mesmo período antes
 * para comparar.
 *
 * DE ONDE SAI CADA NÚMERO
 *   · as ordens do período são as CONCLUÍDAS nele (ou entregues, se nunca
 *     passaram por concluída) — é aí que o trabalho vira dinheiro;
 *   · só contam as linhas aprovadas (OF-03);
 *   · o custo das peças é o custo do artigo no catálogo (hoje); as linhas sem
 *     artigo ou sem custo contam-se à parte, para a margem não mentir;
 *   · o custo da mão-de-obra são as horas do relógio (OF-06) × o preço/hora do
 *     mecânico — sem relógio, as horas vendidas das linhas;
 *   · a taxa de aprovação conta as linhas DECIDIDAS pelo cliente no período.
 */
class IndicadoresDaOficina
{
    public static function calcular(int $tenantId, CarbonInterface $de, CarbonInterface $ate): array
    {
        $inicio = $de->copy()->startOfDay();
        $fim = $ate->copy()->endOfDay();
        $quando = 'COALESCE(o.completed_at, o.delivered_at)';

        $ordens = DB::table('workshop_work_orders as o')
            ->where('o.tenant_id', $tenantId)->whereNull('o.deleted_at')->whereIn('o.status', ['completed', 'delivered'])
            ->whereRaw("$quando BETWEEN ? AND ?", [$inicio, $fim])
            ->selectRaw("o.id, o.total, o.vehicle_id, o.received_at, $quando as fechada")->get();

        $ids = $ordens->pluck('id')->all();
        $n = count($ids);
        $receita = round((float) $ordens->sum('total'), 2);

        $horasNaOficina = $ordens->filter(fn ($o) => $o->received_at && $o->fechada)
            ->map(fn ($o) => max(0, (strtotime($o->fechada) - strtotime($o->received_at)) / 3600));

        $linhas = $n ? DB::table('workshop_work_order_items as i')
            ->leftJoin('invoicing_products as p', 'p.id', '=', 'i.product_id')
            ->whereIn('i.work_order_id', $ids)->where('i.approval', 'approved')
            ->selectRaw("i.type, SUM(i.subtotal) as receita, SUM(CASE WHEN i.type = 'part' AND p.cost > 0 THEN p.cost * i.quantity ELSE 0 END) as custo,"
                . " SUM(CASE WHEN i.type = 'part' AND (p.id IS NULL OR p.cost IS NULL OR p.cost = 0) THEN 1 ELSE 0 END) as sem_custo,"
                . " SUM(CASE WHEN i.type = 'part' AND (p.id IS NULL OR p.cost IS NULL OR p.cost = 0) THEN i.subtotal ELSE 0 END) as receita_sem_custo,"
                . " SUM(CASE WHEN i.type = 'service' THEN i.hours * GREATEST(i.quantity, 1) ELSE 0 END) as horas")
            ->groupBy('i.type')->get()->keyBy('type') : collect();

        // O relógio (OF-06) das ordens do período, com o preço/hora de quem trabalhou.
        $relogio = $n ? DB::table('workshop_time_entries as t')
            ->leftJoin('workshop_mechanics as m', 'm.id', '=', 't.mechanic_id')
            ->whereIn('t.work_order_id', $ids)->whereNotNull('t.ended_at')
            ->selectRaw('SUM(t.minutes) as minutos, SUM(t.minutes / 60 * COALESCE(m.hourly_rate, 0)) as custo')->first() : null;

        $vendidas = round((float) ($linhas['service']->horas ?? 0), 2);
        $trabalhadas = round(((int) ($relogio->minutos ?? 0)) / 60, 2);

        // Sem relógio, o custo da mão-de-obra estima-se pelas horas vendidas × o preço/hora do mecânico.
        $custoMaoDeObra = (float) ($relogio->custo ?? 0);
        $estimado = false;
        if ($trabalhadas == 0 && $n) {
            $custoMaoDeObra = (float) DB::table('workshop_work_order_items as i')
                ->join('workshop_work_orders as o', 'o.id', '=', 'i.work_order_id')
                ->leftJoin('workshop_mechanics as m', 'm.id', '=', DB::raw('COALESCE(i.mechanic_id, o.mechanic_id)'))
                ->whereIn('i.work_order_id', $ids)->where('i.approval', 'approved')->where('i.type', 'service')
                ->selectRaw('SUM(i.hours * GREATEST(i.quantity, 1) * COALESCE(m.hourly_rate, 0)) as custo')->value('custo');
            $estimado = $custoMaoDeObra > 0;
        }

        $receitaMaoDeObra = round((float) ($linhas['service']->receita ?? 0), 2);
        $receitaPecas = round((float) ($linhas['part']->receita ?? 0), 2);
        $custoPecas = round((float) ($linhas['part']->custo ?? 0), 2);
        $receitaPecasComCusto = $receitaPecas - (float) ($linhas['part']->receita_sem_custo ?? 0);

        // As decisões do cliente no período (OF-03).
        $decisoes = DB::table('workshop_work_order_items as i')
            ->join('workshop_work_orders as o', 'o.id', '=', 'i.work_order_id')
            ->where('o.tenant_id', $tenantId)->whereNull('o.deleted_at')
            ->whereIn('i.approval', ['approved', 'declined'])->whereNotNull('i.approval_at')
            ->whereBetween('i.approval_at', [$inicio, $fim])
            ->selectRaw("SUM(CASE WHEN i.approval = 'approved' THEN 1 ELSE 0 END) as aprovadas, SUM(CASE WHEN i.approval = 'declined' THEN 1 ELSE 0 END) as recusadas,"
                . " SUM(CASE WHEN i.approval = 'approved' THEN i.subtotal ELSE 0 END) as valor_aprovado, SUM(CASE WHEN i.approval = 'declined' THEN i.subtotal ELSE 0 END) as valor_recusado")
            ->first();
        $valorDecidido = (float) ($decisoes->valor_aprovado ?? 0) + (float) ($decisoes->valor_recusado ?? 0);

        $recomendacoes = DB::table('workshop_deferred_items')->where('tenant_id', $tenantId)->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'aceite' THEN 1 ELSE 0 END) as aceites, SUM(CASE WHEN status = 'pendente' THEN quantity * unit_price ELSE 0 END) as por_vender")
            ->first();

        $pct = fn (float $parte, float $todo) => $todo > 0 ? round($parte / $todo * 100, 1) : null;

        return [
            'ordens' => $n,
            'viaturas' => $ordens->pluck('vehicle_id')->filter()->unique()->count(),
            'receita' => $receita,
            'ticket_medio' => $n ? round($receita / $n, 2) : null,
            'tempo_medio_horas' => $horasNaOficina->isNotEmpty() ? round($horasNaOficina->avg(), 1) : null,
            'mao_de_obra' => [
                'receita' => $receitaMaoDeObra,
                'custo' => round($custoMaoDeObra, 2),
                'margem' => $pct($receitaMaoDeObra - $custoMaoDeObra, $receitaMaoDeObra),
                'estimado' => $estimado,
                'sem_preco_hora' => $receitaMaoDeObra > 0 && $custoMaoDeObra == 0,
            ],
            'pecas' => [
                'receita' => $receitaPecas,
                'custo' => $custoPecas,
                'margem' => $pct($receitaPecasComCusto - $custoPecas, $receitaPecasComCusto),
                'linhas_sem_custo' => (int) ($linhas['part']->sem_custo ?? 0),
            ],
            'horas' => [
                'vendidas' => $vendidas,
                'trabalhadas' => $trabalhadas,
                'eficiencia' => $trabalhadas > 0 ? (int) round($vendidas / $trabalhadas * 100) : null,
            ],
            'aprovacao' => [
                'aprovadas' => (int) ($decisoes->aprovadas ?? 0),
                'recusadas' => (int) ($decisoes->recusadas ?? 0),
                'valor_aprovado' => round((float) ($decisoes->valor_aprovado ?? 0), 2),
                'valor_recusado' => round((float) ($decisoes->valor_recusado ?? 0), 2),
                'taxa' => $pct((float) ($decisoes->valor_aprovado ?? 0), $valorDecidido),
            ],
            'recomendacoes' => [
                'total' => (int) ($recomendacoes->total ?? 0),
                'aceites' => (int) ($recomendacoes->aceites ?? 0),
                'taxa' => $pct((float) ($recomendacoes->aceites ?? 0), (float) ($recomendacoes->total ?? 0)),
                'por_vender' => round((float) ($recomendacoes->por_vender ?? 0), 2),
            ],
            'satisfacao' => InqueritosDaOficina::resumo($tenantId, $inicio, $fim)['media'],
        ];
    }

    /** A receita e o ticket médio dos últimos 12 meses (ordens fechadas em cada mês). */
    public static function meses(int $tenantId): array
    {
        $desde = now()->subMonths(11)->startOfMonth();
        $porMes = DB::table('workshop_work_orders as o')
            ->where('o.tenant_id', $tenantId)->whereNull('o.deleted_at')->whereIn('o.status', ['completed', 'delivered'])
            ->whereRaw('COALESCE(o.completed_at, o.delivered_at) >= ?', [$desde])
            ->groupByRaw("DATE_FORMAT(COALESCE(o.completed_at, o.delivered_at), '%Y-%m')")
            ->selectRaw("DATE_FORMAT(COALESCE(o.completed_at, o.delivered_at), '%Y-%m') as mes, SUM(o.total) as receita, COUNT(*) as ordens")
            ->get()->keyBy('mes');

        return collect(range(0, 11))->map(function ($m) use ($desde, $porMes) {
            $q = $desde->copy()->addMonths($m);
            $l = $porMes[$q->format('Y-m')] ?? null;

            return [
                'mes' => $q->format('Y-m'),
                'rotulo' => $q->translatedFormat('M/y'),
                'receita' => round((float) ($l->receita ?? 0), 2),
                'ordens' => (int) ($l->ordens ?? 0),
                'ticket_medio' => $l && $l->ordens ? round((float) $l->receita / $l->ordens, 2) : 0,
            ];
        })->all();
    }
}
