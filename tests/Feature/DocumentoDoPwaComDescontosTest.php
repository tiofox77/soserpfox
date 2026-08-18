<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * Descontos e entrega nos documentos criados no PWA.
 *
 * A ORDEM DOS DESCONTOS NÃO É DETALHE: o comercial incide antes do IVA e
 * baixa o imposto; o financeiro incide depois e não lhe toca. Trocá-los dá um
 * imposto diferente no mesmo documento — e é o imposto que vai para a AGT.
 */
class DocumentoDoPwaComDescontosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');
    }

    private function criar(array $extra = []): SalesInvoice
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', array_merge([
            'doc_type'   => 'FT',
            'local_uuid' => 'doc_' . uniqid(),
            'items'      => [[
                'product_id'   => $this->produtoComStock(10)->id,
                'product_name' => 'Artigo',
                'quantity'     => 2,
                'unit_price'   => 1000,
            ]],
        ], $extra))->assertSuccessful()->json();

        return SalesInvoice::withoutGlobalScopes()->findOrFail($r['id']);
    }

    public function test_o_desconto_por_linha_baixa_o_liquido(): void
    {
        $doc = $this->criar(['items' => [[
            'product_id'       => $this->produtoComStock(10)->id,
            'product_name'     => 'Artigo',
            'quantity'         => 2,
            'unit_price'       => 1000,
            'discount_percent' => 10,
        ]]]);

        // 2 x 1000 = 2000, menos 10% = 1800.
        $this->assertEquals(1800, (float) $doc->subtotal);
    }

    public function test_o_desconto_comercial_baixa_tambem_o_imposto(): void
    {
        $semDesconto = $this->criar();
        $comDesconto = $this->criar(['discount_commercial' => 500]);

        $this->assertEquals(500, (float) $comDesconto->discount_commercial);
        $this->assertEquals(1500, (float) $comDesconto->subtotal, 'o líquido tinha de descer');
        $this->assertLessThan(
            (float) $semDesconto->tax_amount,
            (float) $comDesconto->tax_amount,
            'o comercial incide ANTES do IVA'
        );
    }

    public function test_o_desconto_financeiro_nao_toca_no_imposto(): void
    {
        $semDesconto = $this->criar();
        $comDesconto = $this->criar(['discount_financial' => 300]);

        // Desconto de pronto pagamento: sai do total, não da base do imposto.
        $this->assertEquals((float) $semDesconto->tax_amount, (float) $comDesconto->tax_amount);
        $this->assertEquals((float) $semDesconto->total - 300, (float) $comDesconto->total);
    }

    public function test_a_entrega_fica_gravada(): void
    {
        $doc = $this->criar([
            'delivery_date'     => '2026-09-01',
            'delivery_location' => 'Armazém do cliente, Luanda',
        ]);

        $this->assertSame('2026-09-01', $doc->delivery_date->format('Y-m-d'));
        $this->assertSame('Armazém do cliente, Luanda', $doc->delivery_location);
    }

    public function test_um_desconto_maior_que_o_documento_nao_da_total_negativo(): void
    {
        $doc = $this->criar(['discount_commercial' => 999999, 'discount_financial' => 999999]);

        $this->assertGreaterThanOrEqual(0, (float) $doc->total);
    }
}
