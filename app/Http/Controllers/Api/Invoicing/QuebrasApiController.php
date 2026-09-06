<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\Waste;
use App\Models\Product;
use App\Services\Invoicing\QuebraDeStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AS QUEBRAS DE STOCK, para o ecrã em React: registo + relatório.
 *
 * Registar e anular vivem no `QuebraDeStock`, o mesmo que o Livewire chama
 * — é ele que faz o stock descer (e voltar, pelo movimento contrário) e
 * congela o custo. O relatório responde às duas perguntas que justificam
 * registar: QUANTO se perdeu no período, e PORQUÊ.
 */
class QuebrasApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $tenantId = activeTenantId();

        return response()->json([
            'motivos' => collect(Waste::MOTIVOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'armazem_padrao' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('id')->value('id'),
            'permissoes' => ['pode_registar' => (bool) $request->user()?->can('invoicing.stock.edit')],
        ]);
    }

    /** Os artigos físicos, pelo nome, código ou código de barras. */
    public function artigos(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $t = '%' . trim((string) $request->validate(['procura' => ['required', 'string', 'max:100']])['procura']) . '%';

        return response()->json([
            'data' => Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('type', '!=', 'servico')
                ->where(fn ($q) => $q->where('name', 'like', $t)->orWhere('code', 'like', $t)->orWhere('barcode', 'like', $t))
                ->orderBy('name')->limit(8)->get(['id', 'name', 'code', 'unit']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $f = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'motivo' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();
        $de = ($f['de'] ?? now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $ate = ($f['ate'] ?? now()->toDateString()) . ' 23:59:59';

        // O RELATÓRIO é sempre sobre o período — e sem as anuladas, que já não são perdas.
        $doPeriodo = Waste::where('tenant_id', $tenantId)->whereNull('annulled_at')->whereBetween('created_at', [$de, $ate]);

        $porMotivo = (clone $doPeriodo)->selectRaw('reason, COUNT(*) registos, COALESCE(SUM(total_cost), 0) custo')->groupBy('reason')->orderByDesc('custo')->get();
        $porProduto = (clone $doPeriodo)->selectRaw('product_id, COALESCE(SUM(quantity), 0) quantidade, COALESCE(SUM(total_cost), 0) custo')
            ->groupBy('product_id')->orderByDesc('custo')->limit(8)->with('product:id,name,unit')->get();

        $pagina = Waste::where('tenant_id', $tenantId)
            ->with(['product:id,name,unit', 'user:id,name', 'warehouse:id,name'])
            ->whereBetween('created_at', [$de, $ate])
            ->when(($f['motivo'] ?? '') !== '' && ($f['motivo'] ?? 'todos') !== 'todos', fn ($q) => $q->where('reason', $f['motivo']))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Waste $w) => $this->linha($w))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
            'resumo' => [
                'registos' => (clone $doPeriodo)->count(),
                'custo' => round((float) (clone $doPeriodo)->sum('total_cost'), 2),
                'por_motivo' => $porMotivo->map(fn ($m) => ['motivo' => $m->reason, 'rotulo' => __(Waste::MOTIVOS[$m->reason] ?? $m->reason), 'registos' => (int) $m->registos, 'custo' => round((float) $m->custo, 2)])->values(),
                'por_produto' => $porProduto->map(fn ($p) => ['artigo' => $p->product?->name, 'unidade' => $p->product?->unit, 'quantidade' => round((float) $p->quantidade, 3), 'custo' => round((float) $p->custo, 2)])->values(),
            ],
        ]);
    }

    public function registar(Request $request, QuebraDeStock $servico): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.edit');

        $d = $request->validate([
            'product_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'in:' . implode(',', array_keys(Waste::MOTIVOS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $w = $servico->registar($d, activeTenantId(), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['product_id' => [$e->getMessage()]]], 422);
        } catch (\Exception $e) {
            // O guardião do stock fala em Exception plana («Stock insuficiente»).
            return response()->json(['message' => $e->getMessage() . ' — confira o armazém escolhido.', 'errors' => ['quantity' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => $this->linha($w->load(['product:id,name,unit', 'user:id,name', 'warehouse:id,name'])), 'message' => __('Quebra registada — o stock já desceu.')], 201);
    }

    public function anular(Request $request, QuebraDeStock $servico, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.edit');

        $w = Waste::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $servico->anular($w, activeTenantId(), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('Anulada — o stock voltou pelo movimento contrário.')]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function linha(Waste $w): array
    {
        return [
            'id' => $w->id,
            'quando' => optional($w->created_at)->toDateTimeString(),
            'artigo' => $w->product?->name,
            'unidade' => $w->product?->unit,
            'armazem' => $w->warehouse?->name,
            'quantidade' => round((float) $w->quantity, 3),
            'custo_unitario' => round((float) $w->unit_cost, 2),
            'custo' => round((float) $w->total_cost, 2),
            'motivo' => $w->reason,
            'motivo_rotulo' => __(Waste::MOTIVOS[$w->reason] ?? $w->reason),
            'notas' => $w->notes,
            'quem' => $w->user?->name,
            'anulada' => $w->annulled_at !== null,
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
