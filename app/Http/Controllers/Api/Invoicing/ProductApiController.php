<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\ProductResource;
use App\Models\Category;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Os artigos, para o ecrã em React.
 *
 * DUAS REGRAS DESTA CASA QUE AQUI NÃO SE QUEBRAM:
 *
 * 1. O `stock_quantity` NUNCA se escreve numa edição. É um agregado derivado
 *    das linhas de `invoicing_stocks`, que o `StockObserver` mantém. Escrevê-lo
 *    aqui devolvia o valor que estava no ecrã quando ele abriu — revertendo as
 *    vendas que aconteceram entretanto — e criava ajustes fantasma sem
 *    movimento nem rasto. O stock ajusta-se na Gestão de Stock, com movimento
 *    registado. Só na CRIAÇÃO é que a quantidade inicial entra.
 *
 * 2. O imposto sai do catálogo (`tax_rate_id`) ou é isenção com motivo
 *    (`exemption_reason`) — nunca uma taxa escrita à mão. Quem resolve a taxa
 *    de cada linha de documento é o `TaxResolver`, e este ecrã não o
 *    contorna.
 *
 * O QUE ESTE ECRÃ AINDA NÃO FAZ, e é dito para ninguém o descobrir tarde:
 * imagens (destaque e galeria) e os campos de sector (medicamento, vestuário,
 * cosmética). Ficam no ecrã Livewire, que continua na morada de sempre.
 */
class ProductApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->exigir($request, 'invoicing.products.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'in:produto,servico'],
            'categoria' => ['nullable', 'integer'],
            'activo' => ['nullable', 'in:1,0'],
            'so_em_falta' => ['nullable', 'in:1'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        /*
         * A MESMA CONSULTA DO ECRÃ LIVEWIRE, incluindo o que ele NÃO faz.
         *
         * Não se filtra por `module` aqui — o ecrã de sempre também não — para
         * as duas listas mostrarem exactamente os mesmos artigos. Uma API que
         * esconda o que o ecrã mostra é tão errada como uma que mostre a mais.
         *
         * O stock vem SOMADO DAS LINHAS (`withSum`) e não da coluna agregada:
         * é a mesma fonte que a lista de sempre usa, e a única que não mente
         * quando o agregado ficou para trás.
         */
        $query = Product::where('tenant_id', activeTenantId())
            ->withSum('stocks as stock_das_linhas', 'quantity')
            ->with(['category', 'taxRate']);

        if ($procura = ($filtros['procura'] ?? null)) {
            $query->where(function ($q) use ($procura) {
                $q->where('name', 'like', "%{$procura}%")
                    ->orWhere('code', 'like', "%{$procura}%")
                    ->orWhere('sku', 'like', "%{$procura}%")
                    ->orWhere('barcode', 'like', "%{$procura}%");
            });
        }

        $query
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filtros['categoria'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(
                isset($filtros['activo']),
                fn ($q) => $q->where('is_active', (bool) $filtros['activo'])
            )
            // Em falta = gere stock, TEM mínimo definido, e está nele ou
            // abaixo. O mínimo tem de ser > 0: sem isso, todo o artigo com
            // zero de mínimo aparecia sempre em falta.
            ->when(
                ($filtros['so_em_falta'] ?? null) === '1',
                fn ($q) => $q->where('manage_stock', true)
                    ->whereNotNull('stock_min')->where('stock_min', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'stock_min')
            );

        return ProductResource::collection(
            $query->orderBy('name')->paginate($filtros['por_pagina'] ?? 15)->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.create');

        $dados = $this->validar($request);

        // Só na criação. Ver a nota no topo da classe.
        $dados['stock_quantity'] = $request->input('type') === 'servico'
            ? 0
            : (float) $request->input('stock_quantity', 0);

        $artigo = Product::create($dados + ['tenant_id' => activeTenantId()]);

        return (new ProductResource($artigo->load(['category', 'taxRate'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): ProductResource
    {
        $this->exigir($request, 'invoicing.products.edit');

        $artigo = Product::where('tenant_id', activeTenantId())->findOrFail($id);

        /*
         * O AGREGADO NÃO SE TOCA. Nem sequer se aceita do pedido: se o corpo
         * trouxer `stock_quantity`, ignora-se em silêncio — a alternativa era
         * recusar, e recusar um campo que o ecrã não mostra confunde mais do
         * que ajuda. Quem ajusta stock fá-lo na Gestão de Stock, com movimento.
         */
        $artigo->update($this->validar($request, $artigo->id));

        return new ProductResource($artigo->fresh()->load(['category', 'taxRate']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.delete');

        $artigo = Product::where('tenant_id', activeTenantId())->findOrFail($id);

        /*
         * UM ARTIGO QUE JÁ FOI VENDIDO NÃO SE APAGA — DESACTIVA-SE.
         *
         * A linha da factura aponta para ele. Apagá-lo deixava documentos
         * fiscais a referir um artigo que já não existe, e o SAFT com linhas
         * órfãs. Desactivar tira-o do POS e das listas sem mexer no passado.
         */
        $vendido = $artigo->linhasVendidas()->exists();

        if ($vendido) {
            $artigo->update(['is_active' => false]);

            return response()->json([
                'message' => __('Este artigo já foi vendido, por isso foi DESACTIVADO em vez de apagado. Deixa de aparecer no POS e nas listas, e os documentos antigos continuam certos.'),
                'desactivado' => true,
            ]);
        }

        $artigo->delete();

        return response()->json(['message' => __('Artigo apagado.'), 'desactivado' => false]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.view');

        return response()->json([
            'categorias' => Category::where('tenant_id', activeTenantId())
                ->orderBy('name')
                ->get(['id', 'name']),

            // As taxas do catálogo da empresa. O regime fiscal já as afinou —
            // nunca se escreve uma percentagem à mão.
            'taxas' => Tax::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('rate')
                ->get(['id', 'name', 'rate']),

            'unidades' => ['un', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'm2', 'm3', 'cx', 'pct', 'par', 'hora'],

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.products.create'),
                'pode_editar' => (bool) $request->user()?->can('invoicing.products.edit'),
                'pode_apagar' => (bool) $request->user()?->can('invoicing.products.delete'),
            ],
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** As mesmas regras do ecrã Livewire, nos campos que este ecrã trata. */
    private function validar(Request $request, ?int $exceptoId = null): array
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'type' => ['required', 'in:produto,servico'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'category_id' => ['required', 'integer', 'exists:invoicing_categories,id'],
            'tax_type' => ['required', 'in:iva,isento'],
            'tax_rate_id' => ['required_if:tax_type,iva', 'nullable', 'integer', 'exists:invoicing_taxes,id'],
            'exemption_reason' => ['required_if:tax_type,isento', 'nullable', 'string', 'max:255'],
            'manage_stock' => ['nullable', 'boolean'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'stock_max' => ['nullable', 'integer', 'min:0', 'gte:stock_min'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Um SERVIÇO não gere stock. Deixá-lo ligado punha-o a desaparecer do
        // POS assim que a «quantidade» chegasse a zero — e um serviço não tem
        // quantidade nenhuma.
        $dados['manage_stock'] = $dados['type'] === 'servico'
            ? false
            : (bool) ($dados['manage_stock'] ?? true);

        // Um dos dois, nunca os dois.
        $dados['tax_rate_id'] = $dados['tax_type'] === 'iva' ? ($dados['tax_rate_id'] ?? null) : null;
        $dados['exemption_reason'] = $dados['tax_type'] === 'isento' ? ($dados['exemption_reason'] ?? null) : null;

        /*
         * AS COLUNAS QUE NÃO ACEITAM NULL.
         *
         * `cost`, `stock_min` e `is_active` são NOT NULL *com omissão na base*
         * — e uma omissão só se aplica quando a coluna NÃO vem no INSERT.
         * Mandar `null` explicitamente atropela-a e o MySQL recusa: «Column
         * 'cost' cannot be null», 500 no ecrã. Um campo deixado em branco no
         * formulário chega aqui como null, portanto é aqui que se converte.
         */
        foreach (['cost' => 0, 'stock_min' => 0] as $campo => $omissao) {
            if (($dados[$campo] ?? null) === null) {
                $dados[$campo] = $omissao;
            }
        }

        $dados['is_active'] = (bool) ($dados['is_active'] ?? true);

        return $dados;
    }
}
