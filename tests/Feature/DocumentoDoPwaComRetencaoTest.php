<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * Retenção na fonte nos documentos criados no PWA.
 *
 * A retenção NÃO é imposto do documento: é dinheiro que o cliente entrega ao
 * Estado em vez de o entregar a quem factura. Baixa o total a receber e não
 * entra no imposto que vai à AGT.
 */
class DocumentoDoPwaComRetencaoTest extends TenantTestCase
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
                'product_name' => 'Serviço',
                'quantity'     => 1,
                'unit_price'   => 10000,
                'tax_rate'     => 0,
            ]],
        ], $extra))->assertSuccessful()->json();

        return SalesInvoice::withoutGlobalScopes()->findOrFail($r['id']);
    }

    public function test_uma_venda_de_mercadoria_nao_retem_nada(): void
    {
        $doc = $this->criar();

        $this->assertEquals(0, (float) $doc->irt_amount);
        $this->assertFalse((bool) $doc->is_service);
    }

    public function test_prestacao_de_servico_retem_6_virgula_5_por_cento(): void
    {
        $doc = $this->criar(['is_service' => true]);

        // 6,5% de 10 000 = 650.
        $this->assertEquals(650, (float) $doc->irt_amount);
        $this->assertTrue((bool) $doc->is_service);
    }

    public function test_a_percentagem_indicada_manda(): void
    {
        $doc = $this->criar(['is_service' => true, 'withholding_percentage' => 10]);

        $this->assertEquals(1000, (float) $doc->irt_amount);
    }

    public function test_a_retencao_baixa_o_total_a_receber(): void
    {
        $sem = $this->criar();
        $com = $this->criar(['is_service' => true]);

        $this->assertEquals((float) $sem->total - 650, (float) $com->total);
    }

    public function test_a_retencao_nao_mexe_no_imposto_da_agt(): void
    {
        $sem = $this->criar();
        $com = $this->criar(['is_service' => true]);

        // O IVA é o mesmo: a retenção não é imposto do documento.
        $this->assertEquals((float) $sem->tax_amount, (float) $com->tax_amount);
    }

    public function test_uma_retencao_maior_que_o_documento_nao_da_total_negativo(): void
    {
        $doc = $this->criar(['is_service' => true, 'withholding_percentage' => 100]);

        $this->assertGreaterThanOrEqual(0, (float) $doc->total);
    }
}
