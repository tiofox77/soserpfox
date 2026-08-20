<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\PaymentModal;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use Livewire\Livewire;
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
 */
class PagamentoDeFacturaDeCompraTest extends TenantTestCase
{
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
        return Livewire::actingAs($this->user)
            ->test(PaymentModal::class)
            ->call('openPaymentModal', 'purchase', $factura->id)
            ->set('payment_method', 'multicaixa')
            ->set('amount', $valor)
            ->call('registerPayment');
    }

    public function test_pagar_uma_factura_de_compra_grava_o_recibo(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 109168.68);

        $recibo = Receipt::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertNotNull($recibo, 'o recibo da compra tinha de ser gravado');
        $this->assertSame('purchase', $recibo->type);
        $this->assertEqualsWithDelta(109168.68, (float) $recibo->amount_paid, 0.01);
    }

    /**
     * O id da factura de compra vai na coluna DELE.
     *
     * Em `invoice_id` — que aponta para as facturas de venda — a base recusa a
     * linha, e era esse o defeito.
     */
    public function test_o_recibo_fica_ligado_a_factura_de_compra_e_nao_a_uma_venda(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 109168.68);

        $recibo = Receipt::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertSame($factura->id, $recibo->purchase_invoice_id);
        $this->assertNull($recibo->invoice_id, 'nunca em invoice_id: essa coluna é das vendas');
        $this->assertSame($factura->id, $recibo->purchaseInvoice->id);
    }

    public function test_a_factura_de_compra_fica_paga(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 109168.68);

        $this->assertSame('paid', $factura->refresh()->status);
    }

    public function test_um_pagamento_parcial_deixa_a_factura_parcialmente_paga(): void
    {
        $factura = $this->facturaDeCompra();

        $this->pagar($factura, 50000);

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

        $this->pagar($factura, 109168.68);

        $recibo = Receipt::where('tenant_id', $this->tenant->id)->latest('id')->first();

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

        Livewire::actingAs($this->user)
            ->test(PaymentModal::class)
            ->call('openPaymentModal', 'sale', $venda->id)
            ->set('payment_method', 'cash')
            ->set('amount', 1000)
            ->call('registerPayment');

        $recibo = Receipt::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertSame('sale', $recibo->type);
        $this->assertSame($venda->id, $recibo->invoice_id);
        $this->assertNull($recibo->purchase_invoice_id);
    }
}
