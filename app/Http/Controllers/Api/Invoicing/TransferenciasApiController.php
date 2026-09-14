<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\Invoicing\TransferenciaDeStock;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AS TRANSFERÊNCIAS DE STOCK, para os ecrãs em React — entre armazéns (e o
 * ajuste em lote) e entre empresas.
 *
 * Tudo o que mexe no stock vive no `TransferenciaDeStock`, o mesmo que os
 * dois ecrãs Livewire chamam. Aqui valida-se o pedido e serve-se o
 * histórico, agrupado pela referência do lote.
 */
class TransferenciasApiController extends Controller
{
    private const PODE_VER = ['invoicing.warehouse-transfer.view', 'invoicing.warehouse-transfer.create', 'invoicing.stock.edit', 'invoicing.inter-company-transfer.view', 'invoicing.inter-company-transfer.create'];

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigirAlguma($request, self::PODE_VER);

        $u = $request->user();
        $tenantId = activeTenantId();

        return response()->json([
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // As empresas DO UTILIZADOR, menos a activa: só para elas se transfere.
            'empresas' => $u->tenants()->where('tenants.id', '!=', $tenantId)->orderBy('name')->get(['tenants.id', 'tenants.name'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'permissoes' => [
                'pode_transferir' => (bool) $u?->can('invoicing.warehouse-transfer.create'),
                'pode_ajustar' => (bool) $u?->can('invoicing.stock.edit'),
                'pode_entre_empresas' => (bool) $u?->can('invoicing.inter-company-transfer.create'),
            ],
        ]);
    }

    /** Os armazéns de uma empresa destino — que tem de ser do utilizador. */
    public function armazensDaEmpresa(Request $request, int $empresa): JsonResponse
    {
        $this->exigirAlguma($request, ['invoicing.inter-company-transfer.view', 'invoicing.inter-company-transfer.create']);

        abort_unless($request->user()->tenants()->where('tenants.id', $empresa)->where('tenants.id', '!=', activeTenantId())->exists(), 404);

        return response()->json([
            'data' => Warehouse::withoutGlobalScope('tenant')->where('tenant_id', $empresa)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Os artigos para escolher, com o que há na origem — uma consulta, no
     * máximo cinquenta linhas. Sem procura só interessa o que EXISTE na origem.
     */
    public function artigos(Request $request): JsonResponse
    {
        $this->exigirAlguma($request, self::PODE_VER);

        $f = $request->validate(['procura' => ['nullable', 'string', 'max:100'], 'armazem' => ['nullable', 'integer'], 'so_com_stock' => ['nullable', 'boolean']]);
        $tenantId = activeTenantId();
        $tArtigos = (new Product)->getTable();
        $tStock = (new Stock)->getTable();
        $termo = trim((string) ($f['procura'] ?? ''));
        $armazem = $f['armazem'] ?? null;

        $q = Product::where('tenant_id', $tenantId)->where('is_active', true)->select('id', 'name', 'code', 'barcode', 'unit', 'cost')->orderBy('name')->limit(50);

        if ($armazem) {
            $q->addSelect([
                'disponivel' => Stock::select('available_quantity')->whereColumn('product_id', "{$tArtigos}.id")->where('warehouse_id', $armazem)->limit(1),
                'custo_no_armazem' => Stock::select('unit_cost')->whereColumn('product_id', "{$tArtigos}.id")->where('warehouse_id', $armazem)->limit(1),
            ]);
        }

        if ($termo !== '') {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$termo}%")->orWhere('code', 'like', "%{$termo}%")->orWhere('barcode', 'like', "%{$termo}%"));
        } elseif ($armazem && ! empty($f['so_com_stock'])) {
            $q->whereExists(fn ($w) => $w->select(DB::raw(1))->from($tStock)->whereColumn("{$tStock}.product_id", "{$tArtigos}.id")->where("{$tStock}.warehouse_id", $armazem)->where("{$tStock}.quantity", '>', 0));
        }

        return response()->json([
            'data' => $q->get()->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'code' => $a->code, 'unit' => $a->unit,
                'disponivel' => round((float) ($a->disponivel ?? 0), 3),
                'custo' => round((float) ($a->custo_no_armazem ?? $a->cost ?? 0), 2),
            ])->values(),
        ]);
    }

    /** O histórico entre armazéns, um registo por lote (referência). */
    public function historico(Request $request): JsonResponse
    {
        $this->exigirAlguma($request, self::PODE_VER);

        $f = $request->validate(['procura' => ['nullable', 'string', 'max:100'], 'armazem' => ['nullable', 'integer'], 'tipo' => ['nullable', 'in:transfer,adjustment'], 'de' => ['nullable', 'date'], 'ate' => ['nullable', 'date'], 'page' => ['nullable', 'integer', 'min:1']]);

        /*
         * A CHAVE DE CADA LINHA: o lote (MOV/), senão o lote antigo
         * (reference_type + reference_id), senão O PRÓPRIO MOVIMENTO.
         *
         * Agrupava-se só por (reference_id, reference_type), e os movimentos
         * sem nenhum dos dois — o ajuste e a transferência de um artigo no ecrã
         * do stock, anos de correcções — caíam TODOS no mesmo grupo: uma
         * «transferência» sem número com 5476 artigos e 24 986 unidades, com o
         * nome e a hora do último que lá entrou. Parecia uma transferência mal
         * feita; era a lista a somar o que não tinha nada a ver.
         */
        $chave = "COALESCE(batch_reference, IF(reference_id IS NULL, CONCAT('mov:', id), CONCAT(COALESCE(reference_type, ''), ':', reference_id)))";

        $q = StockMovement::where('tenant_id', activeTenantId())
            ->whereIn('type', ['transfer', 'adjustment'])
            ->select(DB::raw("{$chave} as chave"), DB::raw('MAX(reference_id) as reference_id'), DB::raw('MAX(reference_type) as reference_type'), DB::raw('MAX(id) as id'), DB::raw('MIN(id) as primeiro_id'), DB::raw('MAX(product_id) as product_id'), DB::raw('MAX(warehouse_id) as warehouse_id'), DB::raw('MAX(type) as type'), DB::raw('MAX(user_id) as user_id'), DB::raw('MAX(notes) as notes'), DB::raw('MAX(created_at) as created_at'), DB::raw('MAX(batch_reference) as batch_reference'), DB::raw('COUNT(DISTINCT product_id) as products_count'), DB::raw('COUNT(*) as linhas'), DB::raw("SUM(CASE WHEN type = 'transfer' THEN GREATEST(quantity, 0) ELSE ABS(quantity) END) as total_quantity"))
            ->when($f['armazem'] ?? null, fn ($w, $v) => $w->where('warehouse_id', $v))
            ->when($f['tipo'] ?? null, fn ($w, $v) => $w->where('type', $v))
            ->when($f['procura'] ?? null, fn ($w, $v) => $w->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$v}%")->orWhere('code', 'like', "%{$v}%")))
            ->when($f['de'] ?? null, fn ($w, $v) => $w->where('created_at', '>=', $v . ' 00:00:00'))
            ->when($f['ate'] ?? null, fn ($w, $v) => $w->where('created_at', '<=', $v . ' 23:59:59'))
            ->groupBy(DB::raw($chave))
            ->orderByDesc('created_at');

        $pagina = $q->paginate(15)->withQueryString();
        $pagina->load(['warehouse:id,name', 'user:id,name', 'product' => fn ($p) => $p->withTrashed()->select('id', 'name')]);

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($m) => [
                'id' => $m->id,
                'referencia' => $m->batch_reference,
                'reference_id' => $m->reference_id,
                'tipo' => $m->type,
                'tipo_rotulo' => $m->type === 'transfer' ? __('Transferência') : __('Ajuste'),
                'quando' => (string) $m->created_at,
                'armazem' => $m->warehouse?->name,
                'produtos' => (int) $m->products_count,
                // Um movimento avulso (antigo, sem lote): o artigo diz o que foi.
                'movimento_id' => str_starts_with((string) $m->chave, 'mov:') ? (int) $m->id : null,
                'artigo' => (int) $m->products_count === 1 ? $m->product?->name : null,
                'quantidade' => round((float) $m->total_quantity, 3),
                'quem' => $m->user?->name,
                'notas' => $m->notes,
                /*
                 * OS DOIS CAMINHOS PARA O PAPEL DO LOTE, como no ecrã de sempre.
                 *
                 * A PRÉ-VISUALIZAÇÃO abre no browser e é de lá que se imprime;
                 * o PDF descarrega. A migração trouxe só o segundo, e quem
                 * queria conferir antes de imprimir tinha de descarregar um
                 * ficheiro para o abrir.
                 */
                'preview' => $m->batch_reference ? '/invoicing/stock/movimentacao/' . $m->batch_reference . '/preview' : null,
                'pdf' => $m->batch_reference ? '/invoicing/stock/movimentacao/' . $m->batch_reference . '/pdf' : null,
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
        ]);
    }

    /** As linhas de um lote: pela referência MOV/ quando existe, senão pelo reference_id antigo. */
    public function detalhes(Request $request): JsonResponse
    {
        $this->exigirAlguma($request, self::PODE_VER);

        $f = $request->validate(['referencia' => ['nullable', 'string', 'max:40'], 'reference_id' => ['nullable', 'integer'], 'movimento' => ['nullable', 'integer']]);

        $q = StockMovement::where('tenant_id', activeTenantId())->with(['product:id,name,code', 'warehouse:id,name', 'user:id,name']);

        if (! empty($f['referencia'])) {
            $q->where('batch_reference', $f['referencia']);
        } elseif (! empty($f['movimento'])) {
            // Um movimento avulso: só ele (e só se for de transferência ou ajuste).
            $q->whereKey($f['movimento'])->whereNull('batch_reference')->whereIn('type', ['transfer', 'adjustment']);
        } elseif (! empty($f['reference_id'])) {
            $q->whereNull('batch_reference')->where('reference_id', $f['reference_id']);
        } else {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $q->orderBy('id')->get()->map(fn ($m) => [
                'id' => $m->id, 'artigo' => $m->product?->name, 'codigo' => $m->product?->code, 'armazem' => $m->warehouse?->name,
                'quantidade' => round((float) $m->quantity, 3), 'antes' => $m->balance_before !== null ? round((float) $m->balance_before, 3) : null,
                'depois' => $m->balance_after !== null ? round((float) $m->balance_after, 3) : null, 'notas' => $m->notes, 'quem' => $m->user?->name,
            ])->values(),
        ]);
    }

    /** O histórico entre empresas: o que saiu e o que entrou. */
    public function historicoEntreEmpresas(Request $request): JsonResponse
    {
        $this->exigirAlguma($request, ['invoicing.inter-company-transfer.view', 'invoicing.inter-company-transfer.create']);

        $pagina = StockMovement::where('tenant_id', activeTenantId())
            ->where('reference_type', 'inter_company')
            ->whereIn('type', ['transfer', 'in'])
            ->with(['product:id,name,code', 'warehouse:id,name', 'user:id,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($m) => [
                'id' => $m->id, 'quando' => (string) $m->created_at, 'sentido' => $m->type === 'in' ? 'entrada' : 'saida',
                'artigo' => $m->product?->name, 'codigo' => $m->product?->code, 'armazem' => $m->warehouse?->name,
                'quantidade' => round(abs((float) $m->quantity), 3), 'referencia' => $m->batch_reference, 'notas' => $m->notes, 'quem' => $m->user?->name,
                // A pré-visualização e o PDF do lote — o ecrã de sempre tinha-os
                // ao lado da referência, e a migração deixou-os cair.
                'preview' => $m->batch_reference ? '/invoicing/stock/movimentacao/' . $m->batch_reference . '/preview' : null,
                'pdf' => $m->batch_reference ? '/invoicing/stock/movimentacao/' . $m->batch_reference . '/pdf' : null,
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'per_page' => $pagina->perPage(), 'total' => $pagina->total()],
        ]);
    }

    public function entreArmazens(Request $request, TransferenciaDeStock $servico): JsonResponse
    {
        $this->exigir($request, 'invoicing.warehouse-transfer.create');

        $d = $request->validate([
            'de' => ['required', 'integer'], 'para' => ['required', 'integer'], 'notas' => ['nullable', 'string', 'max:500'],
            'itens' => ['required', 'array', 'min:1'], 'itens.*.product_id' => ['required', 'integer'], 'itens.*.product_name' => ['nullable', 'string'], 'itens.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $r = $servico->entreArmazens((int) $d['de'], (int) $d['para'], $d['itens'], $d['notas'] ?? null, activeTenantId(), $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['itens' => [$e->getMessage()]]], 422);
        }

        return response()->json($r + ['pdf' => '/invoicing/stock/movimentacao/' . $r['referencia'] . '/pdf', 'message' => __('Transferência :ref registada.', ['ref' => $r['referencia']])], 201);
    }

    public function ajuste(Request $request, TransferenciaDeStock $servico): JsonResponse
    {
        $this->exigir($request, 'invoicing.stock.edit');

        $d = $request->validate([
            'armazem' => ['required', 'integer'], 'tipo' => ['required', 'in:in,out'], 'motivo' => ['required', 'string', 'max:255'],
            'itens' => ['required', 'array', 'min:1'], 'itens.*.product_id' => ['required', 'integer'], 'itens.*.product_name' => ['nullable', 'string'], 'itens.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $r = $servico->ajustarEmLote((int) $d['armazem'], $d['tipo'], $d['motivo'], $d['itens'], activeTenantId(), $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['itens' => [$e->getMessage()]]], 422);
        }

        return response()->json($r + ['pdf' => '/invoicing/stock/movimentacao/' . $r['referencia'] . '/pdf', 'message' => __('Ajuste :ref registado.', ['ref' => $r['referencia']])], 201);
    }

    public function entreEmpresas(Request $request, TransferenciaDeStock $servico): JsonResponse
    {
        $this->exigir($request, 'invoicing.inter-company-transfer.create');

        $d = $request->validate([
            'de_armazem' => ['required', 'integer'], 'para_empresa' => ['required', 'integer'], 'para_armazem' => ['required', 'integer'], 'notas' => ['required', 'string', 'max:500'],
            'itens' => ['required', 'array', 'min:1'], 'itens.*.product_id' => ['required', 'integer'], 'itens.*.product_name' => ['nullable', 'string'], 'itens.*.quantity' => ['required', 'numeric', 'min:0.01'], 'itens.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ], ['notas.required' => __('Informe o motivo da transferência.')]);

        try {
            $r = $servico->entreEmpresas((int) $d['de_armazem'], (int) $d['para_empresa'], (int) $d['para_armazem'], $d['itens'], $d['notas'], activeTenantId(), $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['itens' => [$e->getMessage()]]], 422);
        }

        return response()->json($r + ['pdf' => '/invoicing/stock/movimentacao/' . $r['referencia_origem'] . '/pdf', 'message' => count($r['resumo']) . ' ' . __('produto(s) transferido(s) para') . ' ' . $r['destino_nome'] . '.'], 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function exigirAlguma(Request $request, array $permissoes): void
    {
        abort_unless($request->user()?->canAny($permissoes), 403, __('Sem permissão para esta operação.'));
    }
}
