<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Restaurant\Waste;
use App\Services\Restaurant\RestaurantStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O STOCK E O DESPERDÍCIO DA COZINHA.
 *
 * DUAS COISAS DIFERENTES COM O MESMO NOME, e é de propósito que aparecem
 * lado a lado:
 *
 *   · O DESPERDÍCIO DE ARMAZÉM — caixa de tomate estragada, leite fora de
 *     prazo. Sai do stock e tem custo.
 *   · O DESPERDÍCIO DE PRODUÇÃO — o prato que foi feito e voltou para trás.
 *     Nasce quando se anula um artigo já produzido, na comanda, e o que ele
 *     mede é outra coisa: erro de serviço, não erro de compra.
 *
 * Quem gere a cozinha precisa de ver os dois; somá-los daria um número que não
 * quer dizer nada.
 */
class StockApiController extends Controller
{
    public function __construct(private readonly RestaurantStockService $stock) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.stock.view');

        $tenantId = activeTenantId();

        return response()->json([
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'is_default'])
                ->map(fn (Warehouse $a) => [
                    'valor' => (string) $a->id, 'rotulo' => $a->name, 'padrao' => (bool) $a->is_default,
                ])->values(),
            'artigos' => Product::where('tenant_id', $tenantId)->where('manage_stock', true)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'unit'])
                ->map(fn (Product $p) => [
                    'valor' => (string) $p->id, 'rotulo' => $p->name, 'unidade' => $p->unit ?: 'UN',
                ])->values(),
            'permissoes' => ['pode_lancar' => (bool) $request->user()?->can('restaurant.stock.waste')],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.stock.view');

        $tenantId = activeTenantId();
        $procura = trim((string) $request->string('procura'));

        $existencias = Stock::with(['product:id,name,unit,stock_min', 'warehouse:id,name'])
            ->where('tenant_id', $tenantId)
            ->when($request->integer('armazem'), fn ($q, $a) => $q->where('warehouse_id', $a))
            ->when($procura !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$procura}%")))
            ->orderBy('available_quantity')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $existencias->map(fn (Stock $s) => [
                'id' => $s->id,
                'artigo' => $s->product?->name ?? '—',
                'unidade' => $s->product?->unit,
                'armazem' => $s->warehouse?->name,
                'disponivel' => (float) $s->available_quantity,
                'minimo' => (float) ($s->product?->stock_min ?? 0),
                // Abaixo do mínimo é o que a cozinha precisa de ver primeiro —
                // por isso a lista vem ordenada pelo que está mais baixo.
                'em_falta' => (float) $s->available_quantity <= (float) ($s->product?->stock_min ?? 0),
            ])->values(),
            'desperdicios' => StockMovement::with('product:id,name,unit')
                ->where('tenant_id', $tenantId)->where('reference_type', 'restaurant_waste')
                ->latest()->limit(50)->get()
                ->map(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'artigo' => $m->product?->name ?? '—',
                    'quantidade' => (float) $m->quantity,
                    'unidade' => $m->product?->unit,
                    'custo' => (float) $m->total_cost,
                    'motivo' => $m->notes,
                    'quando' => $m->created_at?->toIso8601String(),
                ])->values(),
            'producao' => Waste::with(['order:id,order_number', 'orderItem:id,product_name,unit', 'user:id,name'])
                ->where('tenant_id', $tenantId)->latest()->limit(50)->get()
                ->map(fn (Waste $w) => [
                    'id' => $w->id,
                    'comanda' => $w->order?->order_number,
                    'artigo' => $w->orderItem?->product_name ?? '—',
                    'unidade' => $w->orderItem?->unit,
                    'quantidade' => (float) $w->quantity,
                    'motivo' => $w->reason,
                    'quem' => $w->user?->name,
                    'quando' => $w->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    /**
     * Lançar um desperdício de armazém.
     *
     * O MÍNIMO É 0,01 e não 0,001: `invoicing_stock_movements.quantity` é
     * `decimal(10,2)`. Um desperdício de 0,004 era aceite, gravava 0,00 e o
     * stock não mexia — ficava registado um desperdício que não existiu.
     */
    public function desperdicio(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.stock.waste');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'product_id' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], [
            'product_id' => __('artigo'), 'warehouse_id' => __('armazém'),
            'quantity' => __('quantidade'), 'reason' => __('motivo'),
        ]);

        try {
            $this->stock->waste(
                $dados['product_id'], (float) $dados['quantity'], $dados['warehouse_id'],
                $dados['reason'], $tenantId, $request->user()?->id,
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
        }

        return response()->json(['message' => __('Desperdício registado.')], 201);
    }
}
