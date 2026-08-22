<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Services\AGT\DocumentMapper;
use Tests\TenantTestCase;

/**
 * O imposto por linha (taxContribution) tem de bater ao cêntimo com o que a AGT
 * apura, senão vem o E70:
 *
 *   «Valor do imposto "taxContribution" … do artigo na linha (1) não
 *    corresponde ao imposto apurado (X).»
 *
 * A AGT apura o IVA por arredondamento ao cêntimo por EXCESSO (CEIL, DS.120
 * §4.1) sobre base × taxa. O sistema enviava o tax_amount gravado, já
 * arredondado a 2 casas — logo o ceil não tinha fracção para subir e saía
 * menos um cêntimo. Caso real: base 20.006.581,89 × 14% = 2.800.921,4646; a AGT
 * apurou 2.800.921,47 e nós enviávamos 2.800.921,46.
 */
class AgtImpostoPorLinhaTest extends TenantTestCase
{
    private function facturaComBase(float $base, float $taxa = 14): SalesInvoice
    {
        $f = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FT S/000001',
            'invoice_type'   => 'FT',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
            'status'         => 'sent',
            'subtotal'       => $base,
            'net_total'      => $base,
            'tax_payable'    => 0,
            'total'          => $base,
            'gross_total'    => $base,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id,
            'product_name'     => 'Serviço grande',
            'description'      => 'Serviço grande',
            'quantity'         => 1,
            'unit'             => 'UN',
            'unit_price'       => $base,
            'unit_price_base'  => $base,
            'subtotal'         => $base,
            'tax_rate'         => $taxa,
            'credit_amount'    => $base,
            'order'            => 1,
            'tax_country_region' => 'AO',
            'tax_code'         => 'NOR',
        ]);

        return $f->fresh();
    }

    public function test_taxcontribution_e_o_ceil_da_base_vezes_taxa(): void
    {
        // Reprodução exacta do caso Free Dation.
        $factura = $this->facturaComBase(20006581.89);

        $doc = (new DocumentMapper())->map($factura);

        $enviado = (float) $doc['lines'][0]['taxes'][0]['taxContribution'];

        // 20.006.581,89 × 14% = 2.800.921,4646 → CEIL ao cêntimo = 2.800.921,47.
        $this->assertSame(2800921.47, $enviado, 'a AGT apura por ceil; enviar o round dá E70');
        // E nunca o round, que era o que estava a ser recusado.
        $this->assertNotSame(2800921.46, $enviado);
    }

    public function test_os_totais_fecham_com_o_imposto_apurado(): void
    {
        $factura = $this->facturaComBase(20006581.89);

        $doc = (new DocumentMapper())->map($factura);

        $net = (float) $doc['documentTotals']['netTotal'];
        $imposto = (float) $doc['documentTotals']['taxPayable'];
        $bruto = (float) $doc['documentTotals']['grossTotal'];

        // taxPayable é a soma dos taxContribution das linhas (o ceil).
        $this->assertSame(2800921.47, $imposto);
        // grossTotal reconstruído tem de fechar: net + imposto.
        $this->assertSame(round($net + $imposto, 2), $bruto);
    }

    public function test_linha_isenta_nao_leva_imposto(): void
    {
        $factura = $this->facturaComBase(1000, 0);

        $doc = (new DocumentMapper())->map($factura);

        $this->assertSame(0.0, (float) $doc['lines'][0]['taxes'][0]['taxContribution']);
    }
}
