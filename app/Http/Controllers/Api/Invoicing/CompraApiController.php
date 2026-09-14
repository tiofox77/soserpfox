<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Helpers\DocumentConfigHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Warehouse;
use App\Traits\DocumentosPorAutor;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\CalculadoraDeDocumento;
use App\Services\Invoicing\DuplicaDocumento;
use App\Services\Invoicing\EmissorDeCompras;
use App\Services\Invoicing\TaxResolver;
use App\Support\Geografia;
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

        return response()->json($this->paraEditor($this->baseDoAutor()->with('items')->findOrFail($id)));
    }

    /**
     * DUPLICAR: o conteúdo desta compra, para o editor abrir em branco.
     *
     * Não grava nada. Devolve o conteúdo comercial — fornecedor, armazém,
     * linhas com preços, lotes e validades — e o editor abre com ele como
     * rascunho novo. O número, o hash e o estado ficam para trás; o que viaja
     * e o que fica está no `DuplicaDocumento`.
     *
     * NÃO ENTRA STOCK NENHUM AQUI, e é por isso que duplicar uma compra já
     * recebida é seguro: o stock só se mexe quando o duplicado for mesmo
     * gravado, pelo `EmissorDeCompras`, como qualquer compra nova.
     */
    public function duplicar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.purchases.invoices.create');

        $f = $this->baseDoAutor()->with('items')->findOrFail($id);
        $aberta = $this->paraEditor($f);

        return response()->json(DuplicaDocumento::resposta(
            $aberta['documento'],
            $aberta['linhas'],
            // Um duplicado é de hoje; o vencimento volta a combinar-se com o
            // fornecedor e não se herda o prazo de uma factura antiga.
            ['invoice_date' => now()->toDateString(), 'due_date' => null],
            $f,
            $f->invoice_number,
        ));
    }

    /**
     * ANULAR uma compra registada — o caminho certo, porque apagar não existe.
     *
     * A regra inteira (rascunho não se anula, anulada não se reanula, e uma
     * compra com dinheiro pago não se anula sem primeiro desfazer o
     * pagamento) vive no `EmissorDeCompras`. Aqui só se resolve o documento
     * da empresa e se traduz a recusa em 422.
     */
    public function anular(Request $request, EmissorDeCompras $emissor, int $id): JsonResponse
    {
        // Anular É o eliminar de uma factura de compra: é a única forma de a
        // tirar de circulação, e por isso é a permissão de eliminar que manda.
        $this->exigir($request, 'invoicing.purchases.invoices.delete');

        $f = $this->baseDoAutor()->findOrFail($id);

        try {
            $emissor->anular($f);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'estado' => $f->status,
            'message' => __('Factura de compra :n anulada. O stock que tinha entrado foi revertido.', ['n' => $f->invoice_number]),
        ]);
    }

    /**
     * A compra na forma que o editor conhece.
     *
     * Serve o `abrir` e o `duplicar`: uma forma só, para o duplicado herdar
     * exactamente o que a edição herdaria — e mais nada.
     *
     * @return array{documento: array<string,mixed>, linhas: mixed}
     */
    private function paraEditor(PurchaseInvoice $f): array
    {
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return [
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
                // O DESCONTO DE SEMPRE, que soma ao comercial. A base guarda-o,
                // o ecrã pedia-o e a validação aceitava-o — só não voltava ao
                // editor, e a primeira gravação de uma compra que o tivesse
                // apagava-o em silêncio.
                'discount_amount' => (float) ($f->discount_amount ?? 0),
                'discount_financial' => (float) ($f->discount_financial ?? 0),
                'notes' => $f->notes,
                // AS CONDIÇÕES que ficam escritas no documento (prazo, garantia).
                // Gravavam-se e não voltavam: reabrir a compra apagava-as.
                'terms' => $f->terms,
            ],
            'linhas' => $f->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description ?? '',
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->unit_price,
                'discount_percent' => (float) ($i->discount_percent ?? 0),
                'batch_number' => $i->batch_number ?? '',
                // O FABRICO E OS DIAS DE ALERTA do lote: sem eles, reabrir um
                // rascunho perdia a data que separa duas remessas com a mesma
                // validade, e o aviso voltava aos 30 dias por omissão.
                'manufacturing_date' => $data($i->manufacturing_date) ?? '',
                'expiry_date' => $data($i->expiry_date) ?? '',
                'alert_days' => (int) ($i->alert_days ?? 30),
            ])->values(),
        ];
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

            // O armazém marcado como padrão da empresa, para uma compra nova
            // nascer com ele já escolhido — a compra dá entrada de stock e o
            // armazém é obrigatório, por isso escolhê-lo à mão de cada vez era
            // uma paragem em todas as compras.
            'armazem_padrao' => Warehouse::getDefault($tenantId)?->id,

            'regioes' => [
                ['valor' => 'AO', 'rotulo' => 'Angola (continente)'],
                ['valor' => 'AO-CAB', 'rotulo' => 'Cabinda'],
            ],

            'estados' => [
                ['valor' => 'draft', 'rotulo' => __('Rascunho')],
                ['valor' => 'pending', 'rotulo' => __('Por pagar')],
                ['valor' => 'paid', 'rotulo' => __('Paga')],
            ],

            /*
             * O FORNECEDOR RÁPIDO — a factura do fornecedor está na mão e ele
             * ainda não está na ficha. Cria-se aqui, sem largar o registo a
             * meio, pela porta de sempre (`/catalogos/fornecedores`).
             *
             * Criar fornecedores é permissão própria, diferente da de
             * registar compras: quem não a tem não vê o botão.
             */
            'criar_parte' => [
                'tipo' => 'fornecedor',
                'pode' => (bool) $request->user()?->can('invoicing.suppliers.create'),
                'pais_padrao' => Geografia::PAIS_PADRAO,
            ],

            'permissoes' => [
                'pode_criar' => true,
            ],

            // «Imprimir automaticamente ao gravar», das definições da empresa:
            // o ecrã abre o PDF da compra assim que fica registada.
            'imprimir_ao_gravar' => DocumentConfigHelper::shouldAutoPrint(),
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
            'supplier_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('invoicing_suppliers', 'id')->where('tenant_id', activeTenantId())],
            // A compra dá entrada de stock: o armazém é sempre obrigatório.
            'warehouse_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', activeTenantId())],
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
            // O papel da compra e o estado com que ficou: um rascunho não se
            // imprime sozinho, uma compra registada sim.
            'pdf' => '/invoicing/purchases/invoices/' . $f->id . '/pdf',
            'estado' => $f->status,
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
