<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockCount;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Waste;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O PAINEL DO INVENTÁRIO — quanto vale, o que está errado, o que se perdeu.
 *
 *   1. VALOR — o stock a custo: o dinheiro parado nas prateleiras;
 *   2. ERRADO — os NEGATIVOS, que são impossíveis físicos: cada um é uma venda
 *      de coisa que o sistema diz não existir;
 *   3. PERDIDO — as quebras do mês;
 *   4. QUANDO — a última contagem física por armazém, porque UM INVENTÁRIO
 *      NUNCA CONTADO É UM NÚMERO EM QUE NINGUÉM DEVE CONFIAR.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventario.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = activeTenantId();

        $valor = (float) Stock::where('invoicing_stocks.tenant_id', $tenantId)
            ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
            ->where('invoicing_stocks.quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(invoicing_stocks.quantity * invoicing_products.cost), 0) v')
            ->value('v');

        return response()->json([
            'resumo' => [
                'valor' => $valor,
                'artigos_geridos' => Product::where('tenant_id', $tenantId)
                    ->where('is_active', true)->where('manage_stock', true)->count(),
                'negativos' => Stock::where('tenant_id', $tenantId)->where('quantity', '<', 0)->count(),
                'a_zero' => Stock::where('invoicing_stocks.tenant_id', $tenantId)
                    ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
                    ->where('invoicing_products.manage_stock', true)
                    ->where('invoicing_products.is_active', true)
                    ->where('invoicing_stocks.quantity', '=', 0)->count(),
                'quebras_mes' => (float) Waste::where('tenant_id', $tenantId)
                    ->whereNull('annulled_at')
                    ->where('created_at', '>=', now()->startOfMonth())->sum('total_cost'),
            ],
            'negativos' => Stock::where('invoicing_stocks.tenant_id', $tenantId)
                ->where('quantity', '<', 0)
                ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
                ->orderBy('quantity')
                ->limit(10)
                ->get(['invoicing_stocks.quantity', 'invoicing_products.name'])
                ->map(fn ($l) => ['nome' => $l->name, 'quantidade' => (float) $l->quantity])->values(),
            'mais_valiosos' => Stock::where('invoicing_stocks.tenant_id', $tenantId)
                ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
                ->where('invoicing_stocks.quantity', '>', 0)
                ->where('invoicing_products.cost', '>', 0)
                ->selectRaw('invoicing_products.name nome, invoicing_stocks.quantity q, invoicing_products.cost c, (invoicing_stocks.quantity * invoicing_products.cost) valor')
                ->orderByDesc('valor')
                ->limit(10)
                ->get()
                ->map(fn ($l) => [
                    'nome' => $l->nome,
                    'quantidade' => (float) $l->q,
                    'custo' => (float) $l->c,
                    'valor' => (float) $l->valor,
                ])->values(),
            'movimentos' => $this->movimentos($tenantId),
            'contagens' => StockCount::where('tenant_id', $tenantId)
                ->where('status', 'closed')
                ->with('warehouse:id,name')
                ->latest('closed_at')
                ->get()
                ->unique('warehouse_id')
                ->take(8)
                ->map(fn (StockCount $c) => [
                    'id' => $c->id,
                    'armazem' => $c->warehouse?->name,
                    'fechada_em' => $c->closed_at?->format('Y-m-d'),
                    // A IDADE DA VERDADE: um inventário contado há seis meses
                    // não é o mesmo número que um contado ontem.
                    'dias' => $c->closed_at ? (int) $c->closed_at->diffInDays(now()) : null,
                    'acertos' => (int) $c->items_adjusted,
                    'custo' => (float) $c->adjustment_cost,
                ])->values(),
        ]);
    }

    /** Entradas contra saídas dos últimos 30 dias — o pulso do armazém. */
    private function movimentos(int $tenantId): array
    {
        $dias = collect(range(29, 0))->map(fn ($atras) => now()->copy()->subDays($atras));

        $porDia = StockMovement::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->whereIn('type', ['in', 'out'])
            ->selectRaw('DATE(created_at) d, type, COUNT(*) c')
            ->groupBy('d', 'type')->get()->groupBy('d');

        return [
            'etiquetas' => $dias->map(fn ($d) => $d->format('d/m'))->all(),
            'entradas' => $dias->map(fn ($d) => (int) ($porDia->get($d->format('Y-m-d'))
                ?->firstWhere('type', 'in')->c ?? 0))->all(),
            'saidas' => $dias->map(fn ($d) => (int) ($porDia->get($d->format('Y-m-d'))
                ?->firstWhere('type', 'out')->c ?? 0))->all(),
        ];
    }
}
