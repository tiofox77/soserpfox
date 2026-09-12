<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DO RESTAURANTE — a forma da semana, num sítio só.
 *
 * As quatro agregações que aqui vivem são as do ecrã em Blade e mantêm-se
 * como estavam, incluindo o que elas têm de cuidadoso:
 *
 *   · OS DIAS SEM VENDA APARECEM A ZERO. Sem isso, a linha saltava por cima da
 *     segunda-feira fechada e dava-lhe a venda do domingo — um gráfico que
 *     mente sobre o dia mais importante de todos, o que não vendeu nada.
 *   · UMA CONSULTA, NÃO CATORZE. Catorze consultas para desenhar uma linha é
 *     o tipo de coisa que ninguém nota até o restaurante ter dois anos de
 *     comandas.
 */
class PainelApiController extends Controller
{
    /** Das 6 à 1 da manhã: quem fecha às 23h não mostra dezoito horas vazias. */
    private const HORAS = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 0, 1];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('restaurant.dashboard.view'), 403, __('Sem permissão para esta operação.'));

        $dias = max(7, min(90, (int) $request->integer('dias', 14)));
        $tenantId = activeTenantId();

        $hoje = $this->somaDoDia($tenantId, today());
        $ontem = $this->somaDoDia($tenantId, today()->subDay());

        $comandasHoje = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();

        return response()->json([
            'dias' => $dias,
            'resumo' => [
                'mesas' => DiningTable::where('is_active', true)->count(),
                'mesas_ocupadas' => DiningTable::whereIn('status', ['occupied', 'waiting_kitchen', 'served', 'billing'])->count(),
                'comandas_abertas' => Order::open()->count(),
                'venda_hoje' => $hoje,
                'venda_ontem' => $ontem,
                'comandas_hoje' => $comandasHoje,
                // O ticket médio: o número que diz se se está a vender mais ou
                // só a atender mais gente. Duas leituras muito diferentes.
                'ticket_medio' => $comandasHoje > 0 ? round($hoje / $comandasHoje, 2) : 0.0,
            ],
            'mesas_por_estado' => DiningTable::where('is_active', true)
                ->selectRaw('status, COUNT(*) total')
                ->groupBy('status')
                ->get()
                ->map(fn ($l) => [
                    'estado' => $l->status,
                    'rotulo' => __(DiningTable::STATUSES[$l->status] ?? $l->status),
                    'total' => (int) $l->total,
                ])->values(),
            'ultimas' => Order::with(['table:id,name,code', 'waiter:id,name'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'numero' => $o->order_number,
                    'mesa' => $o->table?->name ?? $o->table?->code,
                    'empregado' => $o->waiter?->name,
                    'canal' => $o->channel,
                    'canal_rotulo' => __(Order::CANAIS[$o->channel] ?? $o->channel),
                    'estado' => $o->status,
                    'total' => (float) $o->grand_total,
                    'criada_em' => $o->created_at?->toIso8601String(),
                ])->values(),
            'por_dia' => $this->vendaPorDia($tenantId, $dias),
            'por_hora' => $this->vendaPorHora($tenantId, $dias),
            'pratos' => $this->pratosMaisVendidos($tenantId, $dias),
            'por_mesa' => $this->receitaPorMesa($tenantId, $dias),
        ]);
    }

    private function somaDoDia(int $tenantId, \Illuminate\Support\Carbon $dia): float
    {
        return (float) Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()])
            ->sum('grand_total');
    }

    private function vendaPorDia(int $tenantId, int $dias): array
    {
        $desde = today()->subDays($dias - 1);

        $porDia = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', $desde->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as dia, SUM(grand_total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $dias; $d++) {
            $data = $desde->copy()->addDays($d);

            $etiquetas[] = $data->format('d/m');
            $valores[] = (float) ($porDia[$data->format('Y-m-d')] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** A que horas se vende — a pergunta que decide escalas de pessoal. */
    private function vendaPorHora(int $tenantId, int $dias): array
    {
        $porHora = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '>=', today()->subDays($dias - 1)->startOfDay())
            ->selectRaw('HOUR(created_at) as hora, SUM(grand_total) as total')
            ->groupBy('hora')
            ->pluck('total', 'hora');

        return [
            'etiquetas' => array_map(fn ($h) => sprintf('%02dh', $h), self::HORAS),
            'valores' => array_map(fn ($h) => (float) ($porHora[$h] ?? 0), self::HORAS),
        ];
    }

    private function pratosMaisVendidos(int $tenantId, int $dias): array
    {
        $linhas = DB::table('restaurant_order_items as i')
            ->join('restaurant_orders as o', 'o.id', '=', 'i.order_id')
            ->join('invoicing_products as p', 'p.id', '=', 'i.product_id')
            ->where('i.tenant_id', $tenantId)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('i.kitchen_status', '!=', 'voided')
            ->where('o.created_at', '>=', today()->subDays($dias - 1)->startOfDay())
            ->groupBy('p.id', 'p.name')
            ->selectRaw('p.name as nome, SUM(i.quantity) as quantidade')
            ->orderByDesc('quantidade')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->pluck('quantidade')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /** Quanto rende cada mesa. Uma que nunca aparece aqui está mal colocada. */
    private function receitaPorMesa(int $tenantId, int $dias): array
    {
        $linhas = Order::withoutGlobalScopes()
            ->where('restaurant_orders.tenant_id', $tenantId)
            ->whereNotIn('restaurant_orders.status', ['cancelled'])
            ->where('restaurant_orders.created_at', '>=', today()->subDays($dias - 1)->startOfDay())
            ->whereNotNull('restaurant_orders.table_id')
            ->join('restaurant_tables as m', 'm.id', '=', 'restaurant_orders.table_id')
            ->groupBy('m.id', 'm.name', 'm.code')
            ->selectRaw('COALESCE(m.name, m.code) as nome, SUM(restaurant_orders.grand_total) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->pluck('total')->map(fn ($v) => (float) $v)->all(),
        ];
    }
}
