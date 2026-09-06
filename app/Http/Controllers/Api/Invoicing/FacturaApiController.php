<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Traits\DocumentosPorAutor;
use Illuminate\Support\Facades\DB;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Treasury\PaymentMethod;
use App\Services\Invoicing\CalculadoraDeDocumento;
use App\Services\Invoicing\EmissorDeFacturas;
use App\Services\Invoicing\TaxResolver;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * EMITIR UMA FACTURA DE VENDA (FT ou FR), para o ecrã em React.
 *
 * NÃO HÁ LÓGICA FISCAL AQUI. Descontos, IEC/IS, campos SAFT, FR paga no acto,
 * retenção, hash, stock, tesouraria e fila da AGT vivem no `EmissorDeFacturas`
 * — o mesmo que o componente Livewire chama. Este controlador valida o pedido,
 * monta as linhas com a taxa resolvida no servidor, e chama.
 *
 * A TAXA DE CADA LINHA VEM DO `TaxResolver`, nunca do pedido. O preço pode
 * vir do pedido (é o preço de venda desta factura), a taxa não.
 */
class FacturaApiController extends Controller
{
    use DocumentosPorAutor;

    protected function modeloDoDocumento(): string
    {
        return SalesInvoice::class;
    }

    /**
     * A factura como o editor a precisa: cabeçalho e linhas, e se ainda se
     * pode mexer. Um rascunho edita-se; uma factura emitida abre-se só para
     * ler — rectifica-se com nota de crédito (Decreto 71/25).
     */
    public function abrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.view');

        $f = $this->baseDoAutor()->with('items')->findOrFail($id);
        $retencao = DB::table('invoicing_withholding_taxes')->where('document_type', SalesInvoice::class)->where('document_id', $f->id)->first();
        $extras = LineTax::where('line_type', SalesInvoiceItem::class)->whereIn('line_id', $f->items->pluck('id'))->get()->groupBy('line_id');
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return response()->json([
            'documento' => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'estado' => $f->status,
                'pode_editar' => $f->status === 'draft' || ($f->status === 'pending' && empty($f->saft_hash)),
                'client_id' => $f->client_id,
                'warehouse_id' => $f->warehouse_id,
                'invoice_type' => $f->invoice_type ?: 'FT',
                'series_id' => $f->series_id,
                'invoice_date' => $data($f->invoice_date),
                'due_date' => $data($f->due_date),
                'delivery_date' => $data($f->delivery_date),
                'tax_country_region' => $f->tax_country_region ?? $f->items->first()?->tax_country_region,
                'payment_method' => $f->payment_method,
                'discount_commercial' => (float) ($f->discount_commercial ?? 0),
                'discount_financial' => (float) ($f->discount_financial ?? 0),
                'withholding_type' => $retencao->withholding_tax_type ?? null,
                'withholding_percentage' => (float) ($retencao->withholding_tax_percentage ?? 0),
                'notes' => $f->notes,
                'pdf' => url('invoicing/sales/invoices/' . $f->id . '/pdf'),
            ],
            'linhas' => $f->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description ?? '',
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->unit_price,
                'discount_percent' => (float) ($i->discount_percent ?? 0),
                'iec' => $extras->get($i->id)?->firstWhere('tax_type', 'IEC')?->pautal_code,
                'is' => $extras->get($i->id)?->firstWhere('tax_type', 'IS')?->verba_no,
            ])->values(),
        ]);
    }
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        $tenantId = activeTenantId();

        return response()->json([
            'clientes' => Client::where('tenant_id', $tenantId)->orderBy('name')->limit(500)->get(['id', 'name', 'nif', 'province']),
            'artigos' => Product::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'code', 'price', 'unit', 'type']),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),

            // As séries por tipo: a FR usa a sequência do POS.
            'series' => InvoicingSeries::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('document_type', ['invoice', 'invoice_receipt', 'pos'])
                ->orderBy('series_code')
                ->get(['id', 'series_code', 'name', 'document_type', 'is_default']),

            'formas_de_pagamento' => PaymentMethod::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),

            'retencoes' => collect(EmissorDeFacturas::DESCRICOES_RETENCAO)
                ->map(fn ($d, $c) => ['valor' => $c, 'rotulo' => $d])->values(),

            'regioes' => [
                ['valor' => '', 'rotulo' => __('Pela província do cliente')],
                ['valor' => 'AO', 'rotulo' => 'Angola (continente)'],
                ['valor' => 'AO-CAB', 'rotulo' => 'Cabinda'],
            ],

            'permissoes' => [
                'pode_criar' => true,
            ],
        ]);
    }

    /** Os totais para o ecrã mostrar. Não grava nada. */
    public function calcular(Request $request, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

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

    public function guardar(Request $request, EmissorDeFacturas $emissor): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        return $this->emitirPedido($request, $emissor, null);
    }

    /** Guarda de novo um rascunho. O emissor recusa o que já está finalizado. */
    public function actualizar(Request $request, EmissorDeFacturas $emissor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        return $this->emitirPedido($request, $emissor, $this->baseDoAutor()->findOrFail($id));
    }

    private function emitirPedido(Request $request, EmissorDeFacturas $emissor, ?SalesInvoice $existente): JsonResponse
    {        $dados = $request->validate([
            'client_id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'invoice_type' => ['required', 'in:FT,FR'],
            'series_id' => ['nullable', 'integer'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'is_service' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_commercial' => ['nullable', 'numeric', 'min:0'],
            'discount_financial' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'tax_country_region' => ['nullable', 'in:AO,AO-CAB'],
            'withholding_type' => ['nullable', 'string', 'max:5'],
            'withholding_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'withholding_amount' => ['nullable', 'numeric', 'min:0'],
            // A Fatura-Recibo é paga no acto — exige a forma de pagamento.
            'payment_method' => ['required_if:invoice_type,FR', 'nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:draft,pending'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer', 'exists:invoicing_products,id'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'linhas.*.price' => ['required', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.iec' => ['nullable', 'string', 'max:20'],
            'linhas.*.is' => ['nullable', 'string', 'max:20'],
        ], [
            'payment_method.required_if' => __('A Fatura-Recibo é paga no acto — selecione a forma de pagamento.'),
            'linhas.required' => __('Uma factura sem linhas não é uma factura.'),
            'linhas.min' => __('Uma factura sem linhas não é uma factura.'),
        ]);

        // O armazém só é obrigatório havendo artigos físicos.
        [$linhas, $iec, $is, $temFisicos] = $this->linhasDoPedido($dados['linhas']);

        if ($temFisicos && empty($dados['warehouse_id'])) {
            return response()->json([
                'message' => __('Selecione um armazém — existem produtos físicos no documento.'),
                'errors' => ['warehouse_id' => [__('Obrigatório com artigos físicos.')]],
            ], 422);
        }

        try {
            $r = $emissor->emitir(
                array_merge($dados, ['status' => $dados['status'] ?? 'pending']),
                $linhas,
                $iec,
                $is,
                $existente
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['linhas' => [$e->getMessage()]]], 422);
        }

        $f = $r['factura'];

        return response()->json([
            'id' => $f->id,
            'numero' => $f->invoice_number,
            'total' => round((float) $f->total, 2),
            'agt' => $r['fila']['enfileirado'] ? __('A comunicar à AGT.') : null,
            'abrir' => '/invoicing/sales/invoices/' . $f->id,
            'pdf' => '/invoicing/sales/invoices/' . $f->id . '/pdf',
            'message' => $existente
                ? __('Factura :n actualizada.', ['n' => $f->invoice_number])
                : __('Factura :n emitida.', ['n' => $f->invoice_number]),
        ], $existente ? 200 : 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * As linhas na forma do emissor, com a TAXA resolvida aqui.
     *
     * @return array{0: Collection, 1: array, 2: array, 3: bool}  [linhas, IEC por id, IS por id, há físicos]
     */
    private function linhasDoPedido(array $pedidas): array
    {
        return EmissorDeFacturas::linhasDoPedido($pedidas);
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
