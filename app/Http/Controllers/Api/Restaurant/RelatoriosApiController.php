<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\StockMovement;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS RELATÓRIOS DO RESTAURANTE — um período, e o que aconteceu nele.
 *
 * O QUE SE CONTA COMO VENDA são as comandas FECHADAS (`billed` e
 * `partially_billed`). Uma comanda aberta ainda pode crescer ou ser anulada:
 * contá-la era dar por vendido o que ainda está em cima da mesa.
 *
 * O DESPERDÍCIO vem pelo CUSTO e não pela quantidade — é a única forma de o
 * pôr ao lado da venda e perceber o peso que tem.
 */
class RelatoriosApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('restaurant.reports.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ], [], ['de' => __('data inicial'), 'ate' => __('data final')]);

        $de = ($filtros['de'] ?? now()->startOfMonth()->format('Y-m-d')).' 00:00:00';
        $ate = ($filtros['ate'] ?? now()->format('Y-m-d')).' 23:59:59';

        $tenantId = activeTenantId();

        $noPeriodo = fn () => Order::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$de, $ate]);

        $fechadas = fn () => $noPeriodo()->whereIn('status', ['billed', 'partially_billed']);

        $pratos = OrderItem::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$de, $ate]))
            ->where('kitchen_status', '!=', 'voided')
            ->selectRaw('product_name, SUM(quantity) qty, SUM(line_total) total')
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $porCanal = $fechadas()
            ->selectRaw('channel, COUNT(*) comandas, SUM(grand_total) total, SUM(delivery_fee) entregas')
            ->groupBy('channel')->orderByDesc('total')->get();

        return response()->json([
            'de' => substr($de, 0, 10),
            'ate' => substr($ate, 0, 10),
            'resumo' => [
                'vendas' => (float) $fechadas()->sum('grand_total'),
                'comandas' => $fechadas()->count(),
                'ticket' => round((float) ($fechadas()->avg('grand_total') ?: 0), 2),
                'gorjetas' => (float) $fechadas()->sum('tip_amount'),
                'anuladas' => $noPeriodo()->where('status', 'cancelled')->count(),
                'reservas' => Reservation::withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->whereBetween('reserved_at', [$de, $ate])->count(),
                'desperdicio' => (float) StockMovement::withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->where('reference_type', 'restaurant_waste')
                    ->whereBetween('created_at', [$de, $ate])->sum('total_cost'),
            ],
            'pratos' => $pratos->map(fn ($l) => [
                'nome' => $l->product_name,
                'quantidade' => (float) $l->qty,
                'total' => (float) $l->total,
            ])->values(),
            'canais' => $porCanal->map(fn ($l) => [
                'canal' => $l->channel,
                'rotulo' => __(Order::CANAIS[$l->channel] ?? $l->channel),
                'comandas' => (int) $l->comandas,
                'total' => (float) $l->total,
                'entregas' => (float) $l->entregas,
            ])->values(),
            'grafico_de_pratos' => [
                'etiquetas' => $pratos->pluck('product_name')->all(),
                'valores' => $pratos->pluck('qty')->map(fn ($v) => (float) $v)->all(),
            ],
        ]);
    }
}
