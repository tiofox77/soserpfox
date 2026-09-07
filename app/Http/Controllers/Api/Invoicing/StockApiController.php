<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\Invoicing\MovimentacaoDeStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A GESTÃO DE STOCK, para o ecrã em React.
 *
 * A lista e os cartões saem da MESMA consulta filtrada: escolher um armazém
 * filtra as linhas e os totais juntos, porque quem confere a prateleira lê
 * o número grande. Ajustar, transferir e a movimentação em lote passam pelo
 * `MovimentacaoDeStock`, o mesmo que o Livewire chama.
 */
class StockApiController extends Controller
{
    private const CONSERVACAO = ['ambiente' => 'Ambiente', 'refrigerado' => 'Refrigerado', 'congelado' => 'Congelado'];

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $tenantId = activeTenantId();
        $u = $request->user();

        // A regra do OU: mostra-se a conservação a quem diz trabalhar com
        // mercearia OU a quem já tem artigos marcados.
        $perfis = InvoicingSettings::forTenant($tenantId)->perfisActivos();
        $mostraConservacao = in_array(InvoicingSettings::PERFIL_MERCEARIA, $perfis, true)
            || Product::where('tenant_id', $tenantId)->whereNotNull('storage_conditions')->where('storage_conditions', '<>', '')->exists();

        return response()->json([
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_default']),
            'armazem_padrao' => defaultWarehouseId(),
            'mostra_conservacao' => $mostraConservacao,
            'conservacao' => collect(self::CONSERVACAO)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'permissoes' => [
                'pode_editar' => (bool) $u?->can('invoicing.stock.edit'),
                'pode_transferir' => (bool) $u?->can('invoicing.warehouse-transfer.create'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'armazem' => ['nullable', 'integer'],
            'baixo' => ['nullable', 'boolean'],
            'conservacao' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = $this->consulta($filtros);

        $pagina = (clone $base)->with(['warehouse:id,name', 'product'])->orderBy('id')->paginate(15)->withQueryString();

        $agg = (clone $base)->selectRaw('COUNT(DISTINCT product_id) AS artigos, COALESCE(SUM(quantity), 0) AS quantidade, COALESCE(SUM(quantity * unit_cost), 0) AS valor')->first();
        $baixo = (clone $base)->whereHas('product', $this->stockBaixo())->count();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Stock $s) => $this->linha($s))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
            'resumo' => [
                'artigos' => (int) ($agg->artigos ?? 0),
                'quantidade' => round((float) ($agg->quantidade ?? 0), 3),
                'valor' => round((float) ($agg->valor ?? 0), 2),
                'baixo' => (int) $baixo,
            ],
        ]);
    }

    /** Os artigos para a movimentação em lote, pelo nome, código ou código de barras. */
    public function artigos(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.edit');

        $dados = $request->validate(['procura' => ['required', 'string', 'max:100'], 'armazem' => ['nullable', 'integer']]);
        $tenantId = activeTenantId();
        $t = '%' . $dados['procura'] . '%';

        $artigos = Product::where('tenant_id', $tenantId)->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $t)->orWhere('code', 'like', $t)->orWhere('sku', 'like', $t)->orWhere('barcode', 'like', $t))
            ->orderBy('name')->limit(15)
            ->get(['id', 'name', 'code', 'sku', 'barcode', 'unit', 'cost', 'net_content', 'stock_quantity']);

        $noArmazem = ! empty($dados['armazem'])
            ? Stock::where('tenant_id', $tenantId)->where('warehouse_id', $dados['armazem'])->whereIn('product_id', $artigos->pluck('id'))->pluck('quantity', 'product_id')
            : collect();

        return response()->json([
            'data' => $artigos->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'code' => $a->code ?: ($a->sku ?: $a->barcode),
                'unit' => $a->unit,
                'net_content' => $a->net_content,
                'cost' => round((float) ($a->cost ?? 0), 2),
                'actual' => round((float) ($noArmazem[$a->id] ?? $a->stock_quantity ?? 0), 3),
            ])->values(),
        ]);
    }

    public function movimentos(Request $request, MovimentacaoDeStock $stock, int $produto): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.view');

        $tenantId = activeTenantId();
        abort_unless(Product::where('tenant_id', $tenantId)->whereKey($produto)->exists(), 404);

        return response()->json([
            'data' => $stock->movimentos($produto, $tenantId)->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'quando' => optional($m->created_at)->toDateTimeString(),
                'tipo' => $m->type,
                'tipo_rotulo' => $this->tipoRotulo($m->type),
                'quantidade' => round((float) $m->quantity, 3),
                'saldo_depois' => $m->balance_after !== null ? round((float) $m->balance_after, 3) : null,
                'armazem' => $m->warehouse?->name,
                'lote' => $m->batch_reference,
                'notas' => $m->notes,
                'quem' => $m->user?->name,
            ])->values(),
        ]);
    }

    public function ajustar(Request $request, MovimentacaoDeStock $stock): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.edit');

        $dados = $request->validate([
            'stock_id' => ['required', 'integer'],
            'nova_quantidade' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $linha = Stock::where('tenant_id', activeTenantId())->findOrFail($dados['stock_id']);
        $stock->ajustar((int) $linha->warehouse_id, (int) $linha->product_id, (float) $dados['nova_quantidade'], $dados['notas'] ?? null);

        return response()->json(['data' => $this->linha($linha->fresh(['warehouse', 'product'])), 'message' => __('Stock ajustado.')]);
    }

    public function transferir(Request $request, MovimentacaoDeStock $stock): JsonResponse
    {
        $this->exigir($request, 'invoicing.warehouse-transfer.create');

        $tenantId = activeTenantId();
        $linha = Stock::where('tenant_id', $tenantId)->findOrFail((int) $request->input('stock_id'));

        $dados = $request->validate([
            'stock_id' => ['required', 'integer'],
            // Com filtro de empresa: sem ele, um id alheio passava.
            'para_armazem_id' => ['required', 'integer', Rule::notIn([$linha->warehouse_id]), Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
            // 0,01 e não 0,001: a coluna é decimal(10,2); 0,004 gravava 0,00 e
            // criava no destino uma linha fantasma a zero.
            'quantidade' => ['required', 'numeric', 'min:0.01', 'max:' . (float) $linha->available_quantity],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'quantidade.min' => __('A quantidade mínima a transferir é 0,01.'),
            'quantidade.max' => __('Só há :q disponível na origem.', ['q' => (float) $linha->available_quantity]),
            'para_armazem_id.not_in' => __('O destino tem de ser outro armazém.'),
        ]);

        try {
            $stock->transferir((int) $linha->warehouse_id, (int) $dados['para_armazem_id'], (int) $linha->product_id, (float) $dados['quantidade'], $dados['notas'] ?? null);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['quantidade' => [$e->getMessage()]]], 422);
        }

        return response()->json(['message' => __('Transferência realizada.')]);
    }

    /** A movimentação em lote: entradas e saídas manuais, com referência (MOV/AAAA/NNNNNN). */
    public function entrada(Request $request, MovimentacaoDeStock $stock): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'armazem_id' => ['required', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.product_id' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'itens.*.product_name' => ['nullable', 'string', 'max:255'],
            'itens.*.op' => ['required', 'in:add,sub'],
            'itens.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'itens.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'itens.required' => __('Adicione pelo menos um produto.'),
            'itens.*.quantity.min' => __('A quantidade deve ser maior que zero.'),
        ]);

        $r = $stock->registarLote((int) $dados['armazem_id'], $dados['itens'], $dados['notas'] ?? null, $tenantId);

        if ($r['ok'] === 0) {
            return response()->json(['message' => __('Não foi possível registar nenhum produto.'), 'errors' => ['itens' => $r['erros']]], 422);
        }

        return response()->json([
            'referencia' => $r['referencia'],
            'ok' => $r['ok'],
            'erros' => $r['erros'],
            'pdf' => '/invoicing/stock/movimentacao/' . $r['referencia'] . '/pdf',
            'message' => empty($r['erros'])
                ? __('Movimentação :r registada: :n produto(s).', ['r' => $r['referencia'], 'n' => $r['ok']])
                : __('Movimentação :r parcialmente registada: :n produto(s) OK.', ['r' => $r['referencia'], 'n' => $r['ok']]),
        ], 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function consulta(array $f): Builder
    {
        $tenantId = activeTenantId();
        $q = Stock::where('tenant_id', $tenantId)->whereHas('product')->whereHas('warehouse');

        if (! empty($f['armazem'])) {
            $q->where('warehouse_id', $f['armazem']);
        }
        if (! empty($f['procura'])) {
            $t = '%' . $f['procura'] . '%';
            $q->whereHas('product', fn ($p) => $p->where('name', 'like', $t)->orWhere('code', 'like', $t));
        }
        if (! empty($f['baixo'])) {
            $q->whereHas('product', $this->stockBaixo());
        }
        if (! empty($f['conservacao'])) {
            // O artigo não é tenant-scoped: o filtro de empresa vai à mão e qualificado.
            $q->whereHas('product', fn ($p) => $p->where('invoicing_products.tenant_id', $tenantId)->porConservacao($f['conservacao']));
        }

        return $q;
    }

    private function stockBaixo(): \Closure
    {
        return fn ($q) => $q->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min');
    }

    private function linha(Stock $s): array
    {
        return [
            'id' => $s->id,
            'product_id' => $s->product_id,
            'artigo' => $s->product?->name,
            'codigo' => $s->product?->code,
            'unidade' => $s->product?->unit,
            'conteudo' => $s->product?->net_content,
            'conservacao' => $s->product?->storage_conditions,
            'conservacao_rotulo' => $s->product?->storage_conditions ? __(self::CONSERVACAO[$s->product->storage_conditions] ?? $s->product->storage_conditions) : null,
            'warehouse_id' => $s->warehouse_id,
            'armazem' => $s->warehouse?->name,
            'quantidade' => round((float) $s->quantity, 3),
            'disponivel' => round((float) $s->available_quantity, 3),
            /*
             * O RESERVADO, que a lista em Blade mostrava em coluna própria.
             *
             * É o que já está comprometido e ainda não saiu — quem olha para a
             * quantidade sem ver isto acredita que tem mais do que tem, e
             * promete a um cliente o que já é de outro. Vem contado de cá para
             * o ecrã não fazer a subtracção por sua conta.
             */
            'reservado' => round(max(0, (float) $s->quantity - (float) $s->available_quantity), 3),
            'minimo' => round((float) ($s->product?->stock_min ?? 0), 3),
            'baixo' => (float) $s->quantity <= (float) ($s->product?->stock_min ?? 0),
            'custo' => round((float) $s->unit_cost, 2),
            'valor' => round((float) $s->quantity * (float) $s->unit_cost, 2),
        ];
    }

    private function tipoRotulo(?string $tipo): string
    {
        return match ($tipo) {
            'in' => __('Entrada'),
            'out' => __('Saída'),
            'transfer' => __('Transferência'),
            'adjustment' => __('Ajuste'),
            default => (string) $tipo,
        };
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
