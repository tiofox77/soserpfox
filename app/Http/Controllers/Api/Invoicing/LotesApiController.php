<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\Invoicing\GestorDeLotes;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS LOTES E AS VALIDADES, para o ecrã em React.
 *
 * Criar, corrigir e apagar vivem no `GestorDeLotes`, o mesmo que o Livewire
 * chama. Ver é a permissão do stock; escrever são as dos lotes.
 */
class LotesApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $tenantId = activeTenantId();
        $u = $request->user();

        return response()->json([
            'artigos' => Product::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->limit(1000)->get(['id', 'name', 'unit']),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'estados' => [
                ['valor' => 'active', 'rotulo' => __('Activos')],
                ['valor' => 'expiring_soon', 'rotulo' => __('A expirar em breve')],
                ['valor' => 'expired', 'rotulo' => __('Expirados')],
            ],
            'permissoes' => [
                'pode_criar' => (bool) $u?->can('invoicing.product-batches.create'),
                'pode_editar' => (bool) $u?->can('invoicing.product-batches.edit'),
                'pode_apagar' => (bool) $u?->can('invoicing.product-batches.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'produto' => ['nullable', 'integer'],
            'armazem' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $pagina = ProductBatch::with(['product:id,name,unit', 'warehouse:id,name'])
            ->where('tenant_id', $tenantId)
            ->when($f['procura'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('batch_number', 'like', "%{$v}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$v}%"))))
            ->when($f['produto'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['armazem'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($f['estado'] ?? null, function ($q, $v) {
                if ($v === 'expiring_soon') {
                    $q->expiringSoon();
                } elseif ($v === 'expired') {
                    $q->expired();
                } else {
                    $q->where('status', $v);
                }
            })
            ->orderBy('expiry_date')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (ProductBatch $b) => $this->linha($b))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
            'resumo' => [
                'activos' => ProductBatch::where('tenant_id', $tenantId)->active()->count(),
                'a_expirar' => ProductBatch::where('tenant_id', $tenantId)->expiringSoon()->count(),
                'expirados' => ProductBatch::where('tenant_id', $tenantId)->expired()->count(),
            ],
        ]);
    }

    public function guardar(Request $request, GestorDeLotes $gestor): JsonResponse
    {
        $this->exigir($request, 'invoicing.product-batches.create');

        $d = $request->validate($gestor->regras());
        $this->daEmpresa($d);

        $b = $gestor->criar($d, activeTenantId());

        return response()->json(['data' => $this->linha($b->load(['product:id,name,unit', 'warehouse:id,name'])), 'message' => __('Lote criado.')], 201);
    }

    public function actualizar(Request $request, GestorDeLotes $gestor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.product-batches.edit');

        $b = ProductBatch::where('tenant_id', activeTenantId())->findOrFail($id);
        $d = $request->validate($gestor->regras());
        $this->daEmpresa($d);

        $gestor->actualizar($b, $d);

        return response()->json(['data' => $this->linha($b->fresh(['product:id,name,unit', 'warehouse:id,name'])), 'message' => __('Lote actualizado.')]);
    }

    public function apagar(Request $request, GestorDeLotes $gestor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.product-batches.delete');

        $b = ProductBatch::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $gestor->apagar($b);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('Lote apagado.')]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function daEmpresa(array $d): void
    {
        $tenantId = activeTenantId();

        abort_unless(Product::where('tenant_id', $tenantId)->whereKey($d['product_id'])->exists(), 422, __('Artigo desconhecido nesta empresa.'));

        if (! empty($d['warehouse_id'])) {
            abort_unless(Warehouse::where('tenant_id', $tenantId)->whereKey($d['warehouse_id'])->exists(), 422, __('Armazém desconhecido nesta empresa.'));
        }
    }

    private function linha(ProductBatch $b): array
    {
        $dias = $b->expiry_date ? (int) now()->startOfDay()->diffInDays($b->expiry_date, false) : null;

        return [
            'id' => $b->id,
            'product_id' => $b->product_id,
            'artigo' => $b->product?->name,
            'unidade' => $b->product?->unit,
            'warehouse_id' => $b->warehouse_id,
            'armazem' => $b->warehouse?->name,
            'batch_number' => $b->batch_number,
            'manufacturing_date' => optional($b->manufacturing_date)->toDateString(),
            'expiry_date' => optional($b->expiry_date)->toDateString(),
            'dias' => $dias,
            'quantity' => round((float) $b->quantity, 3),
            'quantity_available' => round((float) $b->quantity_available, 3),
            'cost_price' => round((float) $b->cost_price, 2),
            'alert_days' => (int) $b->alert_days,
            'notes' => $b->notes,
            'status' => $b->status,
            'pode_apagar' => (float) $b->quantity_available >= (float) $b->quantity,
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
