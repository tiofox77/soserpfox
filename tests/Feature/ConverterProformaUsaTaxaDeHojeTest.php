<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesProformaItem;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Converter uma proforma em factura usa a taxa de HOJE.
 *
 * A factura é um documento fiscal novo, com data de hoje. Uma proforma feita
 * antes de a empresa mudar de regime tem a taxa antiga gravada na linha, e
 * copiá-la fazia nascer hoje uma factura a liquidar IVA que a empresa já não
 * pode cobrar — corrigível só por nota de crédito.
 */
class ConverterProformaUsaTaxaDeHojeTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function proformaComLinhaA14(Product $artigo): SalesProforma
    {
        $p = SalesProforma::create([
            'tenant_id'       => $this->tenant->id,
            'client_id'       => $this->clienteEmpresa()->id,
            'proforma_number' => 'PR TESTE/' . random_int(1000, 9999),
            'proforma_date'   => now()->subMonth(),
            'status'          => 'draft',
            'subtotal'        => 1000,
            'tax_amount'      => 140,
            'discount_amount' => 0,
            'total'           => 1140,
            'created_by'      => $this->user->id,
        ]);

        SalesProformaItem::create([
            'sales_proforma_id' => $p->id,
            'product_id'        => $artigo->id,
            'product_name'      => $artigo->name,
            'quantity'          => 1,
            'unit_price'        => 1000,
            'subtotal'          => 1000,
            'discount_amount'   => 0,
            'tax_rate'          => 14,
            'tax_amount'        => 140,
            'total'             => 1140,
            'order'             => 1,
        ]);

        return $p->fresh('items');
    }

    public function test_artigo_isento_hoje_gera_factura_sem_iva(): void
    {
        $artigo = $this->produtoComStock(10);
        $artigo->update(['tax_type' => 'isento', 'tax_rate_id' => null, 'exemption_reason' => 'M04']);

        $factura = $this->proformaComLinhaA14($artigo)->convertToInvoice();

        $linha = $factura->items()->first();

        $this->assertEquals(0, (float) $linha->tax_rate, 'a taxa tinha de ser a de hoje, não a da proforma');
        $this->assertEquals(0, (float) $linha->tax_amount);
        $this->assertSame('M04', $linha->tax_exemption_code, 'a AGT recusa uma linha a 0% sem motivo');
        $this->assertNotEmpty($linha->tax_exemption_reason);
    }

    public function test_os_totais_da_factura_seguem_as_linhas_e_nao_a_proforma(): void
    {
        $artigo = $this->produtoComStock(10);
        $artigo->update(['tax_type' => 'isento', 'tax_rate_id' => null, 'exemption_reason' => 'M04']);

        $factura = $this->proformaComLinhaA14($artigo)->convertToInvoice()->fresh();

        // O cabeçalho nascia com os 1140 da proforma enquanto as linhas já
        // somavam 1000 — o documento saía a mentir sobre si próprio.
        $this->assertEquals(0, (float) $factura->tax_amount);
        $this->assertEquals(1000, (float) $factura->total);
    }

    public function test_um_artigo_que_continua_com_iva_mantem_a_taxa(): void
    {
        $artigo = $this->produtoComStock(10);

        $factura = $this->proformaComLinhaA14($artigo)->convertToInvoice();
        $linha = $factura->items()->first();

        // Não é "pôr tudo a zero": é perguntar. Se o artigo continua sujeito,
        // a factura sai com imposto na mesma.
        $esperado = (float) \App\Services\Invoicing\TaxResolver::forProductId($artigo->id, $this->tenant->id)['rate'];

        $this->assertEquals($esperado, (float) $linha->tax_rate);
    }
}
