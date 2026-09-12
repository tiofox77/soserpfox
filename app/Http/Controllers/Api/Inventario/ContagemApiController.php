<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\StockCount;
use App\Models\Invoicing\StockCountItem;
use App\Models\Invoicing\Warehouse;
use App\Services\Invoicing\ContagemFisica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A CONTAGEM FÍSICA: contar a prateleira, comparar com o sistema, acertar.
 *
 * O ecrã vive em DOIS MODOS: sem contagem aberta mostra o histórico e o botão
 * de abrir; com uma aberta mostra a lista de artigos para escrever o contado, e
 * a diferença aparece na hora.
 *
 * UMA CONTAGEM ABERTA RETOMA-SE SOZINHA. A meio de contar quinhentos artigos,
 * sair do ecrã e voltar não pode recomeçar do zero — e é por isso que a porta
 * do «em curso» existe: o ecrã pergunta primeiro se há uma aberta.
 *
 * E O FECHO MOSTRA O RESUMO ANTES DO BOTÃO: quantos acertos vão acontecer e
 * quanto custam. Ninguém deve fechar um inventário às cegas.
 */
class ContagemApiController extends Controller
{
    public function __construct(private readonly ContagemFisica $servico) {}

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('inventario.contagem.manage'), 403,
            __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /** O que o ecrã precisa de saber ao abrir: há contagem em curso? */
    public function index(Request $request): JsonResponse
    {
        $this->exigir($request);

        $aberta = StockCount::forTenant()->where('status', 'open')
            ->with('warehouse:id,name')->first();

        return response()->json([
            'aberta' => $aberta ? $this->linha($aberta) : null,
            'historico' => StockCount::forTenant()
                ->whereIn('status', ['closed', 'cancelled'])
                ->with(['warehouse:id,name', 'opener:id,name'])
                ->latest()->limit(12)->get()
                ->map(fn (StockCount $c) => $this->linha($c))->values(),
            'armazens' => Warehouse::forTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($a) => ['valor' => (string) $a->id, 'rotulo' => $a->name])->values(),
        ]);
    }

    private function linha(StockCount $c): array
    {
        return [
            'id' => $c->id,
            'estado' => $c->status,
            'estado_rotulo' => match ($c->status) {
                'open' => __('Em curso'), 'closed' => __('Fechada'),
                'cancelled' => __('Cancelada'), default => $c->status,
            },
            'warehouse_id' => $c->warehouse_id,
            'armazem' => $c->warehouse?->name,
            'aberta_em' => $c->created_at?->format('Y-m-d H:i'),
            'fechada_em' => $c->closed_at?->format('Y-m-d H:i'),
            'quem_abriu' => $c->opener?->name,
            'notas' => $c->notes,
            'contados' => (int) $c->items_counted,
            'acertos' => (int) $c->items_adjusted,
            'custo' => (float) $c->adjustment_cost,
        ];
    }

    /**
     * ABRIR CONGELA O ESPERADO.
     *
     * A partir daqui, o que o sistema diz ter fica gravado linha a linha: é
     * contra esse retrato que a contagem se compara, e não contra um stock que
     * continua a mexer-se enquanto se conta.
     */
    public function abrir(Request $request): JsonResponse
    {
        $this->exigir($request);

        $dados = $request->validate([
            'warehouse_id' => ['required',
                Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', activeTenantId())],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [], ['warehouse_id' => __('armazém')]);

        try {
            $c = $this->servico->abrir(
                (int) $dados['warehouse_id'], activeTenantId(), $request->user()?->id,
                trim($dados['notas'] ?? '') ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Contagem aberta — o esperado ficou congelado agora. Boa contagem.'),
            'data' => $this->linha($c->fresh('warehouse')),
        ], 201);
    }

    /** As linhas da contagem aberta, com o filtro do ecrã. */
    public function linhas(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $contagem = StockCount::forTenant()->with('warehouse:id,name')->findOrFail($id);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'filtro' => ['nullable', Rule::in(['todos', 'por_contar', 'com_diferenca'])],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        /*
         * QUALIFICADO, porque a lista junta `invoicing_products` para ordenar
         * por nome e as duas tabelas têm `tenant_id` — sem o prefixo, o MySQL
         * responde «Column 'tenant_id' in where clause is ambiguous».
         */
        $base = fn () => StockCountItem::where('invoicing_stock_count_items.tenant_id', activeTenantId())
            ->where('stock_count_id', $contagem->id);

        $lista = $base()
            ->with('product:id,name,code,unit')
            ->when(($filtros['filtro'] ?? 'todos') === 'por_contar',
                fn ($q) => $q->whereNull('counted_quantity'))
            ->when(($filtros['filtro'] ?? 'todos') === 'com_diferenca',
                fn ($q) => $q->whereNotNull('counted_quantity')
                    ->whereColumn('counted_quantity', '!=', 'expected_quantity'))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->whereHas('product', fn ($p) => $p->where('name', 'like', $t)
                    ->orWhere('code', 'like', $t)->orWhere('barcode', 'like', $t));
            })
            ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stock_count_items.product_id')
            ->orderBy('invoicing_products.name')
            ->select('invoicing_stock_count_items.*')
            ->paginate($filtros['por_pagina'] ?? 50);

        return response()->json([
            'contagem' => $this->linha($contagem),
            'data' => collect($lista->items())->map(fn (StockCountItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'artigo' => $i->product?->name,
                'codigo' => $i->product?->code,
                'unidade' => $i->product?->unit,
                'esperado' => (float) $i->expected_quantity,
                'contado' => $i->counted_quantity !== null ? (float) $i->counted_quantity : null,
                'diferenca' => $i->counted_quantity !== null
                    ? round((float) $i->counted_quantity - (float) $i->expected_quantity, 4)
                    : null,
                'custo' => (float) $i->unit_cost,
            ])->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'progresso' => [
                'total' => $base()->count(),
                'contados' => $base()->whereNotNull('counted_quantity')->count(),
                'diferencas' => $base()->whereNotNull('counted_quantity')
                    ->whereColumn('counted_quantity', '!=', 'expected_quantity')->count(),
            ],
        ]);
    }

    public function contar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $dados = $request->validate([
            'product_id' => ['required', 'integer'],
            // NULO É «AINDA NÃO CONTEI», e não «contei zero». São coisas
            // diferentes: a primeira não gera acerto nenhum; a segunda apaga o
            // stock do artigo.
            'contado' => ['nullable', 'numeric', 'min:0'],
        ]);

        $contagem = StockCount::forTenant()->findOrFail($id);

        try {
            $item = $this->servico->contar(
                $contagem, (int) $dados['product_id'],
                $dados['contado'] !== null ? (float) $dados['contado'] : null,
                activeTenantId(), $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'contado' => $item->counted_quantity !== null ? (float) $item->counted_quantity : null,
            'diferenca' => $item->counted_quantity !== null
                ? round((float) $item->counted_quantity - (float) $item->expected_quantity, 4)
                : null,
        ]);
    }

    /**
     * O RESUMO QUE O FECHO MOSTRA ANTES DO BOTÃO.
     *
     * Quantos acertos vão acontecer e quanto custam — para ninguém fechar um
     * inventário às cegas. Uma confirmação do browser não tem como dizer isto.
     */
    public function resumoDoFecho(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $contagem = StockCount::forTenant()->findOrFail($id);

        $linhas = StockCountItem::where('invoicing_stock_count_items.tenant_id', activeTenantId())
            ->where('stock_count_id', $contagem->id)
            ->whereNotNull('counted_quantity')
            ->get(['expected_quantity', 'counted_quantity', 'unit_cost']);

        $comDiferenca = $linhas->filter(
            fn ($l) => abs((float) $l->counted_quantity - (float) $l->expected_quantity) > 0.0001,
        );

        return response()->json([
            'contados' => $linhas->count(),
            'acertos' => $comDiferenca->count(),
            'custo' => round($comDiferenca->sum(
                fn ($l) => abs((float) $l->counted_quantity - (float) $l->expected_quantity) * (float) $l->unit_cost,
            ), 2),
        ]);
    }

    public function fechar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $contagem = StockCount::forTenant()->findOrFail($id);

        try {
            $fechada = $this->servico->fechar($contagem, activeTenantId(), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Fechada: :c contado(s), :a acerto(s), :v Kz de diferença.', [
                'c' => $fechada->items_counted,
                'a' => $fechada->items_adjusted,
                'v' => number_format((float) $fechada->adjustment_cost, 2, ',', '.'),
            ]),
            'data' => $this->linha($fechada->fresh('warehouse')),
        ]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $contagem = StockCount::forTenant()->findOrFail($id);

        try {
            $this->servico->cancelar($contagem, activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json(['message' => __('Cancelada — nada foi mexido no stock.')]);
    }

    /** As diferenças de uma contagem fechada, linha a linha. */
    public function diferencas(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        $contagem = StockCount::forTenant()->with(['warehouse:id,name', 'opener:id,name'])->findOrFail($id);

        return response()->json([
            'data' => $this->linha($contagem),
            'ajustadas' => StockCountItem::where('invoicing_stock_count_items.tenant_id', activeTenantId())
                ->where('stock_count_id', $contagem->id)
                ->whereNotNull('adjustment_movement_id')
                ->with('product:id,name,unit')
                ->get()
                ->map(fn (StockCountItem $i) => [
                    'id' => $i->id,
                    'artigo' => $i->product?->name,
                    'unidade' => $i->product?->unit,
                    'esperado' => (float) $i->expected_quantity,
                    'contado' => (float) $i->counted_quantity,
                    'diferenca' => round((float) $i->counted_quantity - (float) $i->expected_quantity, 4),
                    'custo' => (float) $i->unit_cost,
                ])->values(),
        ]);
    }
}
