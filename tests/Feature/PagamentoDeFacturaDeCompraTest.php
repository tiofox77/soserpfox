<?php

namespace Tests\Feature;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use App\Models\Treasury\Transaction;
use Tests\TenantTestCase;

/**
 * Pagar uma factura de COMPRA.
 *
 * Nunca funcionou. A tabela `invoicing_receipts` serve os dois tipos de
 * recibo, mas só tinha `invoice_id`, com chave estrangeira para as facturas de
 * VENDA. Num recibo de compra escrevia-se lá o id de uma factura de compra e a
 * base recusava a linha inteira:
 *
 *   SQLSTATE[23000] ... a foreign key constraint fails
 *   (`invoicing_receipts_invoice_id_foreign` → `invoicing_sales_invoices`)
 *
 * Ninguém deu por isso porque o erro ficava no log e o ecrã dizia só "Erro ao
 * registrar pagamento". Foi preciso a captura de erros (`erros_do_sistema`)
 * para o ver, e nessa altura já havia alguém há 45 minutos a tentar lançar um
 * pagamento de 109.168,68 Kz numa farmácia.
 *
 * O modal Livewire deu lugar ao das listas em React, que paga por
 * `POST /api/v1/invoicing/react/pagamentos/{tipo}/{factura}`. A regra vive no
 * `RegistoDePagamento` — a mesma para os dois caminhos — e é por essa porta
 * que se prova.
 */
class PagamentoDeFacturaDeCompraTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/pagamentos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.receipts.create');
    }

    private function fornecedor(): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Distribuidora Farmacêutica',
            'is_active' => true,
        ]);
    }

    private function facturaDeCompra(float $total = 109168.68): PurchaseInvoice
    {
        return PurchaseInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $this->fornecedor()->id,
            'invoice_number' => 'FC-' . uniqid(),
            'invoice_date'   => now(),
            'due_date'       => now()->addDays(30),
            'subtotal'       => $total,
            'total'          => $total,
            'paid_amount'    => 0,
            'status'         => 'pending',
        ]);
    }

    private function pagar(PurchaseInvoice $factura, float $valor)
    {
        return $this->postJson(self::RAIZ . '/purchase/' . $factura->id, [
            'amount' => $valor,
            'payment_method' => 'multicaixa',
        ]);
    }

    private function ultimoRecibo(): ?Receipt
    {
        return Receipt::where('tenant_id', $this->tenant->id)->latest('id')->first();
    }

    /**
     * O RECIBO GRAVA-SE, E O ID DA COMPRA VAI NA COLUNA DELE.
     *
     * Em `invoice_id` — que aponta para as facturas de venda — a base recusa a
     * linha, e era esse o defeito.
     */
    public function test_pagar_uma_factura_de_compra_grava_o_recibo_na_coluna_dele(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 109168.68)->assertCreated();

        $recibo = $this->ultimoRecibo();

        $this->assertNotNull($recibo, 'o recibo da compra tinha de ser gravado');
        $this->assertSame('purchase', $recibo->type);
        $this->assertEqualsWithDelta(109168.68, (float) $recibo->amount_paid, 0.01);

        $this->assertSame($factura->id, $recibo->purchase_invoice_id);
        $this->assertNull($recibo->invoice_id, 'nunca em invoice_id: essa coluna é das vendas');
        $this->assertSame($factura->id, $recibo->purchaseInvoice->id);

        $this->assertSame('paid', $factura->refresh()->status);
    }

    public function test_um_pagamento_parcial_deixa_a_factura_parcialmente_paga(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 50000)->assertCreated();

        $this->assertSame('partially_paid', $factura->refresh()->status);
    }

    /**
     * Um recibo de compra NÃO é documento fiscal emitido pela empresa.
     *
     * É dinheiro que ela pagou a um fornecedor. Submetê-lo à AGT seria
     * declarar uma despesa como receita. Nunca aconteceu porque pagar uma
     * compra rebentava antes de lá chegar — agora que funciona, tem de estar
     * travado.
     */
    public function test_o_recibo_de_compra_nao_e_submetido_a_agt(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 109168.68)->assertCreated();

        $recibo = $this->ultimoRecibo();

        $this->assertNull($recibo->agt_status, 'um recibo de compra não vai à AGT');
        $this->assertNull($recibo->agt_reference);
    }

    /** E o de venda continua a funcionar como sempre. */
    public function test_pagar_uma_venda_continua_a_usar_a_coluna_das_vendas(): void
    {
        // Um recibo de VENDA é documento fiscal e precisa de série própria
        // ('receipt'). A TenantTestCase cria FT e FR, não RC.
        \App\Models\Invoicing\InvoicingSeries::create([
            'tenant_id'       => $this->tenant->id,
            'series_code'     => 'RC',
            'name'            => 'RC (teste)',
            'document_type'   => 'receipt',
            'agt_environment' => 'sandbox',
            'is_default'      => true,
            'is_active'       => true,
        ]);

        $venda = SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'invoice_number' => 'FT-' . uniqid(),
            'invoice_date'   => now(),
            'due_date'       => now()->addDays(30),
            'subtotal'       => 1000,
            'total'          => 1000,
            'paid_amount'    => 0,
            'status'         => 'pending',
            'created_by'     => $this->user->id,
        ]);

        $this->postJson(self::RAIZ . '/sale/' . $venda->id, [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertCreated();

        $recibo = $this->ultimoRecibo();

        $this->assertSame('sale', $recibo->type);
        $this->assertSame($venda->id, $recibo->invoice_id);
        $this->assertNull($recibo->purchase_invoice_id);
    }

    /**
     * PAGAR LANÇA SEMPRE NA TESOURARIA. É por isto que há uma porta só.
     *
     * Existiram dois caminhos para dar uma factura por paga: este, que emite o
     * recibo e lança o movimento, e um «marcar como paga» que punha o estado a
     * `paid` e mais nada. O segundo deixava dinheiro sem rasto — a factura
     * dizia-se paga e a caixa não sabia de nada. Foi removido.
     *
     * Este ensaio guarda as três coisas que TÊM de acontecer juntas: o recibo
     * (que é o documento que se entrega), o movimento de tesouraria (que é
     * onde o dinheiro fica registado) e a factura saldada.
     */
    public function test_pagar_uma_compra_lanca_recibo_e_movimento_de_tesouraria(): void
    {
        $factura = $this->facturaDeCompra();

        $antes = Transaction::where('tenant_id', $this->tenant->id)->count();

        $this->pagar($factura, 109168.68)->assertCreated();

        $recibo = $this->ultimoRecibo();
        $this->assertNotNull($recibo, 'sem recibo não há documento para entregar');

        $movimentos = Transaction::where('tenant_id', $this->tenant->id)->count();
        $this->assertSame($antes + 1, $movimentos, 'o dinheiro que saiu tem de ficar na tesouraria');

        $mov = Transaction::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();

        $this->assertSame('expense', $mov->type, 'pagar a um fornecedor é saída');
        $this->assertEqualsWithDelta(109168.68, (float) $mov->amount, 0.01);
        $this->assertNotEmpty($mov->transaction_number, 'e com número próprio, gerado pelo modelo');

        $this->assertSame('paid', $factura->refresh()->status);
        $this->assertEqualsWithDelta((float) $factura->total, (float) $factura->paid_amount, 0.01);
    }
}
