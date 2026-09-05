<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
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

        $dados = $request->validate([
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
                $is
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
            'message' => __('Factura :n emitida.', ['n' => $f->invoice_number]),
        ], 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * As linhas na forma do emissor, com a TAXA resolvida aqui.
     *
     * @return array{0: Collection, 1: array, 2: array, 3: bool}  [linhas, IEC por id, IS por id, há físicos]
     */
    private function linhasDoPedido(array $pedidas): array
    {
        $tenantId = activeTenantId();
        $iec = [];
        $is = [];
        $temFisicos = false;

        $linhas = collect($pedidas)->values()->map(function (array $p, int $i) use ($tenantId, &$iec, &$is, &$temFisicos) {
            $artigo = ! empty($p['product_id'])
                ? Product::where('tenant_id', $tenantId)->find($p['product_id'])
                : null;

            if ($artigo && ($artigo->type ?? 'produto') !== 'servico') {
                $temFisicos = true;
            }

            $imposto = TaxResolver::forProduct($artigo, $tenantId);

            // O emissor indexa o IEC/IS pelo `id` da linha; uma linha livre
            // recebe um id próprio para não colidir com artigos.
            $id = $artigo?->id ?? ('livre_' . $i);

            if (! empty($p['iec'])) {
                $iec[$id] = $p['iec'];
            }
            if (! empty($p['is'])) {
                $is[$id] = $p['is'];
            }

            return (object) [
                'id' => $id,
                'name' => $artigo?->name ?? ($p['description'] ?? ''),
                'price' => round((float) $p['price'], 2),
                'quantity' => (float) $p['quantity'],
                'attributes' => [
                    'description' => $p['description'] ?? null,
                    'unit' => $artigo?->unit ?? 'UN',
                    'discount_percent' => (float) ($p['discount_percent'] ?? 0),
                    'tax_rate' => (float) $imposto['rate'],
                    'exemption_reason' => $imposto['exemption_code'],
                ],
            ];
        });

        return [$linhas, $iec, $is, $temFisicos];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
