<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Warehouse;
use App\Traits\DocumentosPorAutor;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\CalculadoraDeDocumento;
use App\Services\Invoicing\EmissorDeCompras;
use App\Services\Invoicing\TaxResolver;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * REGISTAR UMA FACTURA DE COMPRA, para o ecrã em React.
 *
 * NÃO HÁ LÓGICA DE NEGÓCIO AQUI. Linhas com lotes, totais, hash, a entrada do
 * stock (pelo observer, com o estado posto no fim) e o custo do artigo vivem
 * no `EmissorDeCompras` — o mesmo que o componente Livewire chama. Este
 * controlador valida o pedido, monta as linhas com a taxa resolvida no
 * servidor, e chama.
 *
 * O PREÇO É O DA COMPRA, e vem do pedido: é o que o fornecedor cobrou. A
 * taxa não vem do pedido — vem do `TaxResolver`.
 */
class CompraApiController extends Controller
{
    use DocumentosPorAutor;

    protected function modeloDoDocumento(): string
    {
        return PurchaseInvoice::class;
    }

    /** A compra como o editor a precisa. Só um rascunho se altera: a registada já deu entrada do stock. */
    public function abrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.purchases.invoices.view');

        $f = $this->baseDoAutor()->with('items')->findOrFail($id);
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return response()->json([
            'documento' => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'estado' => $f->status,
                'pode_editar' => $f->status === 'draft',
                'supplier_id' => $f->supplier_id,
                'warehouse_id' => $f->warehouse_id,
                'invoice_date' => $data($f->invoice_date),
                'due_date' => $data($f->due_date),
                'tax_country_region' => $f->tax_country_region ?? $f->items->first()?->tax_country_region ?? 'AO',
                'is_service' => (bool) ($f->is_service ?? false),
                'discount_commercial' => (float) ($f->discount_commercial ?? 0),
                'discount_financial' => (float) ($f->discount_financial ?? 0),
                'notes' => $f->notes,
            ],
            'linhas' => $f->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description ?? '',
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->unit_price,
                'discount_percent' => (float) ($i->discount_percent ?? 0),
                'batch_number' => $i->batch_number ?? '',
                'expiry_date' => $data($i->expiry_date) ?? '',
            ])->values(),
        ]);
    }
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.purchases.invoices.create');

        $tenantId = activeTenantId();

        return response()->json([
            'fornecedores' => Supplier::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']),

            // O preço proposto na linha é o CUSTO conhecido, não o preço de venda.
            'artigos' => Product::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'code', 'cost', 'unit', 'type'])
                ->map(fn ($a) => [
                    'id' => $a->id, 'name' => $a->name, 'code' => $a->code,
                    'cost' => round((float) $a->cost, 2), 'unit' => $a->unit, 'type' => $a->type,
                ]),

            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),

            'regioes' => [
                ['valor' => 'AO', 'rotulo' => 'Angola (continente)'],
                ['valor' => 'AO-CAB', 'rotulo' => 'Cabinda'],
            ],

            'estados' => [
                ['valor' => 'draft', 'rotulo' => __('Rascunho')],
                ['valor' => 'pending', 'rotulo' => __('Por pagar')],
                ['valor' => 'paid', 'rotulo' => __('Paga')],
            ],

            'permissoes' => [
                'pode_criar' => true,
            ],
        ]);
    }

    /** Os totais para o ecrã mostrar. Não grava nada. */
    public function calcular(Request $request, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->exigir($request, 'invoicing.purchases.invoices.create');

        $dados = $request->validate([
            'linhas' => ['array'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.price' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_commercial' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_financial' => ['nullable', 'numeric', 'min:0'],
            'is_service' => ['nullable', 'boolean'],
        ]);

        return response()->json($calculadora->calcular(
            $dados['linhas'] ?? [],
            (float) ($dados['discount_commercial'] ?? 0),
            (float) ($dados['discount_amount'] ?? 0),
            (float) ($dados['discount_financial'] ?? 0),
            (bool) ($dados['is_service'] ?? false)
        ));
    }

    public function guardar(Request $request, EmissorDeCompras $emissor): JsonResponse
    {
        $this->exigir($request, 'invoicing.purchases.invoices.create');

        return $this->registarPedido($request, $emissor, null);
    }

    /** Guarda de novo um rascunho. Uma compra registada já deu entrada do stock: não se mexe. */
    public function actualizar(Request $request, EmissorDeCompras $emissor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.purchases.invoices.create');

        $existente = $this->baseDoAutor()->findOrFail($id);

        if ($existente->status !== 'draft') {
            return response()->json(['message' => __('Só um rascunho se altera — esta compra já foi registada e deu entrada do stock.')], 422);
        }

        return $this->registarPedido($request, $emissor, $existente);
    }

    private function registarPedido(Request $request, EmissorDeCompras $emissor, ?PurchaseInvoice $existente): JsonResponse
    {        $dados = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:invoicing_suppliers,id'],
            // A compra dá entrada de stock: o armazém é sempre obrigatório.
            'warehouse_id' => ['required', 'integer', 'exists:invoicing_warehouses,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'is_service' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_commercial' => ['nullable', 'numeric', 'min:0'],
            'discount_financial' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'tax_country_region' => ['nullable', 'in:AO,AO-CAB'],
            'status' => ['nullable', 'in:draft,pending,paid'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer', 'exists:invoicing_products,id'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'linhas.*.price' => ['required', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.batch_number' => ['nullable', 'string', 'max:100'],
            'linhas.*.manufacturing_date' => ['nullable', 'date'],
            'linhas.*.expiry_date' => ['nullable', 'date'],
            'linhas.*.alert_days' => ['nullable', 'integer', 'min:0'],
        ], [
            'supplier_id.required' => __('Escolha o fornecedor.'),
            'warehouse_id.required' => __('Escolha o armazém — a compra dá entrada de stock.'),
            'linhas.required' => __('Uma factura sem linhas não é uma factura.'),
            'linhas.min' => __('Uma factura sem linhas não é uma factura.'),
        ]);

        try {
            $f = $emissor->emitir(
                array_merge($dados, ['status' => $dados['status'] ?? 'pending']),
                $this->linhasDoPedido($dados['linhas']),
                $existente
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['linhas' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'id' => $f->id,
            'numero' => $f->invoice_number,
            'total' => round((float) $f->total, 2),
            'abrir' => '/invoicing/purchases/invoices',
            'message' => $existente
                ? __('Factura de compra :n actualizada.', ['n' => $f->invoice_number])
                : __('Factura de compra :n registada.', ['n' => $f->invoice_number]),
        ], $existente ? 200 : 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** As linhas na forma do emissor, com a TAXA resolvida aqui. */
    private function linhasDoPedido(array $pedidas): Collection
    {
        $tenantId = activeTenantId();

        return collect($pedidas)->values()->map(function (array $p, int $i) use ($tenantId) {
            $artigo = ! empty($p['product_id'])
                ? Product::where('tenant_id', $tenantId)->find($p['product_id'])
                : null;

            $imposto = TaxResolver::forProduct($artigo, $tenantId);

            return (object) [
                'id' => $artigo?->id ?? ('livre_' . $i),
                'name' => $artigo?->name ?? ($p['description'] ?? ''),
                'price' => round((float) $p['price'], 2),
                'quantity' => (float) $p['quantity'],
                'attributes' => [
                    'unit' => $artigo?->unit ?? 'UN',
                    'discount_percent' => (float) ($p['discount_percent'] ?? 0),
                    'tax_rate' => (float) $imposto['rate'],
                    'batch_number' => $p['batch_number'] ?? null,
                    'manufacturing_date' => $p['manufacturing_date'] ?? null,
                    'expiry_date' => $p['expiry_date'] ?? null,
                    'alert_days' => $p['alert_days'] ?? 30,
                ],
            ];
        });
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
