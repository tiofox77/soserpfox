<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use App\Services\AGT\AutoSubmissao;
use App\Traits\DocumentosPorAutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REGISTAR UM RECIBO.
 *
 * O recibo parece o mais simples dos emissores e é o que tem mais armadilhas
 * juntas. As quatro que este ficheiro respeita:
 *
 * 1. O PAGAMENTO LANÇA-SE SOZINHO. O `paid_amount` da factura é actualizado
 *    pelos ganchos do modelo `Receipt` (created/updated/deleted), por
 *    lançamento DIFERENCIAL. Aqui não se toca nele — recalcular o absoluto foi
 *    o que quase destruiu 6,8 milhões numa empresa e zerou 1220 facturas do
 *    balcão, que são pagas sem recibo nenhum.
 *
 * 2. CADA TIPO NA SUA COLUNA. `invoice_id` tem chave estrangeira para as
 *    facturas de VENDA; o id de uma compra escrito ali faz a base recusar a
 *    linha. As compras vão em `purchase_invoice_id`.
 *
 * 3. O RECIBO É DOCUMENTO FISCAL (RC/RG) e vai à AGT — mas DEPOIS do commit.
 *    O recibo já está gravado e uma falha da AGT não o pode desfazer.
 *
 * 4. O NÚMERO é do modelo, com a série da empresa. Não se escreve aqui.
 */
class ReciboApiController extends Controller
{
    use DocumentosPorAutor;

    protected function modeloDoDocumento(): string
    {
        return Receipt::class;
    }

    /** Um recibo aberto para consulta. É documento fiscal: não se edita. */
    public function mostrar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.receipts.view');

        $r = $this->baseDoAutor()->findOrFail($id);
        $venda = $r->type === 'sale';
        $factura = $venda
            ? SalesInvoice::where('tenant_id', activeTenantId())->find($r->invoice_id)
            : PurchaseInvoice::where('tenant_id', activeTenantId())->find($r->purchase_invoice_id ?? $r->invoice_id);

        return response()->json(['recibo' => [
            'id' => $r->id,
            'numero' => $r->receipt_number,
            'type' => $r->type,
            'estado' => $r->status,
            'parte' => $venda ? Client::where('tenant_id', activeTenantId())->find($r->client_id)?->name : Supplier::where('tenant_id', activeTenantId())->find($r->supplier_id)?->name,
            'factura' => $factura?->invoice_number,
            'payment_date' => $r->payment_date instanceof \DateTimeInterface ? $r->payment_date->format('Y-m-d') : (string) $r->payment_date,
            'payment_method' => $r->payment_method,
            'amount_paid' => (float) $r->amount_paid,
            'reference' => $r->reference,
            'notes' => $r->notes,
            'pdf' => url('invoicing/receipts/' . $r->id . '/pdf'),
        ]]);
    }
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.receipts.view');

        return response()->json([
            'clientes' => Client::where('tenant_id', activeTenantId())
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']),

            'fornecedores' => Supplier::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']),

            'formas' => [
                ['valor' => 'cash', 'rotulo' => __('Numerário')],
                ['valor' => 'transfer', 'rotulo' => __('Transferência')],
                ['valor' => 'check', 'rotulo' => __('Cheque')],
                ['valor' => 'card', 'rotulo' => __('Cartão')],
                ['valor' => 'tpa', 'rotulo' => __('TPA')],
                ['valor' => 'other', 'rotulo' => __('Outro')],
            ],

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.receipts.create'),
            ],
        ]);
    }

    /**
     * As facturas que ainda têm alguma coisa por receber.
     *
     * PELO SALDO E NÃO PELO NOME DO ESTADO. Nomear os estados que entram
     * escondia as `sent` e as `overdue` — que são justamente as que se querem
     * receber. Escreve-se ao contrário.
     */
    public function facturas(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.receipts.view');

        $dados = $request->validate([
            'tipo' => ['required', 'in:sale,purchase'],
            'parte_id' => ['nullable', 'integer'],
        ]);

        $compra = $dados['tipo'] === 'purchase';

        $query = ($compra ? PurchaseInvoice::query() : SalesInvoice::query())
            ->where('tenant_id', activeTenantId())
            ->whereNotIn('status', ['draft', 'cancelled', 'credited'])
            ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) > 0.01');

        if (! empty($dados['parte_id'])) {
            $query->where($compra ? 'supplier_id' : 'client_id', $dados['parte_id']);
        }

        // Uma FR do balcão é paga no acto e nunca tem recibo: oferecê-la aqui
        // é um convite a receber duas vezes.
        if (! $compra) {
            $query->where(function ($q) {
                $q->whereNull('invoice_type')->orWhere('invoice_type', '!=', 'FR');
            });
        }

        return response()->json([
            'data' => $query->orderByDesc('invoice_date')->limit(200)->get()->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'data' => optional($f->invoice_date)->toDateString(),
                'total' => round((float) $f->total, 2),
                'pago' => round((float) $f->paid_amount, 2),
                'falta' => max(0, round((float) $f->total - (float) $f->paid_amount, 2)),
            ])->values(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.receipts.create');

        $dados = $request->validate([
            'type' => ['required', 'in:sale,purchase'],
            'client_id' => ['required_if:type,sale', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('invoicing_clients', 'id')->where('tenant_id', activeTenantId())],
            'supplier_id' => ['required_if:type,purchase', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('invoicing_suppliers', 'id')->where('tenant_id', activeTenantId())],
            'invoice_id' => ['nullable', 'integer'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:30'],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $venda = $dados['type'] === 'sale';

        /*
         * NÃO SE RECEBE MAIS DO QUE FALTA.
         *
         * Sem isto, um recibo de 100.000 numa factura de 570 punha o
         * `paid_amount` acima do total e a factura passava a mostrar um saldo
         * negativo em todos os mapas. O travão é aqui, antes do lançamento.
         */
        if (! empty($dados['invoice_id'])) {
            $factura = $venda
                ? SalesInvoice::where('tenant_id', activeTenantId())->find($dados['invoice_id'])
                : PurchaseInvoice::where('tenant_id', activeTenantId())->find($dados['invoice_id']);

            abort_unless($factura, 422, __('Factura não encontrada nesta empresa.'));

            $falta = max(0, round((float) $factura->total - (float) $factura->paid_amount, 2));

            if (round((float) $dados['amount_paid'], 2) > $falta + 0.01) {
                return response()->json([
                    'message' => __('A factura :n só tem :falta por receber. Não se pode receber :quer.', [
                        'n' => $factura->invoice_number,
                        'falta' => number_format($falta, 2, ',', '.'),
                        'quer' => number_format((float) $dados['amount_paid'], 2, ',', '.'),
                    ]),
                    'errors' => ['amount_paid' => [__('Mais do que o que falta receber.')]],
                ], 422);
            }
        }

        $recibo = DB::transaction(fn () => Receipt::create([
            'tenant_id' => activeTenantId(),
            'type' => $dados['type'],
            'client_id' => $venda ? ($dados['client_id'] ?? null) : null,
            'supplier_id' => $venda ? null : ($dados['supplier_id'] ?? null),
            // Cada tipo na SUA coluna — ver a nota no topo da classe.
            'invoice_id' => $venda ? ($dados['invoice_id'] ?? null) : null,
            'purchase_invoice_id' => $venda ? null : ($dados['invoice_id'] ?? null),
            'payment_date' => $dados['payment_date'],
            'payment_method' => $dados['payment_method'],
            'amount_paid' => $dados['amount_paid'],
            'reference' => $dados['reference'] ?? null,
            'notes' => $dados['notes'] ?? null,
            'status' => 'issued',
            'created_by' => auth()->id(),
        ]));

        // DEPOIS DO COMMIT. O recibo já existe e uma falha da AGT não o desfaz.
        $agt = AutoSubmissao::submeter($recibo);

        return response()->json([
            'id' => $recibo->id,
            'numero' => $recibo->receipt_number,
            'agt' => $agt['enviado'] ? __('Submetido à AGT.') : ($agt['erro'] ? __('Por submeter à AGT: :e', ['e' => $agt['erro']]) : null),
            'abrir' => '/invoicing/receipts',
            'message' => __('Recibo :n criado.', ['n' => $recibo->receipt_number]),
        ], 201);
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
