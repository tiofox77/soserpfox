<?php

namespace Tests\Feature;

use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * IEC e Imposto de Selo nos documentos criados no PWA.
 *
 * O dispositivo manda a ESCOLHA — o código pautal, a verba — e nunca o valor.
 * Se o aparelho calculasse, o documento ficava com dois apuramentos do mesmo
 * imposto e a AGT recusa com "taxContribution não corresponde ao imposto
 * apurado".
 */
class DocumentoDoPwaComIecESeloTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');
    }

    private function pautalCom(float $percentagem): string
    {
        $codigo = 'P' . random_int(100000, 999999);

        DB::table('agt_iec_pautal_codes')->insert([
            'pautal_code' => $codigo, 'description' => 'Teste IEC', 'category' => 'Teste',
            'rate_percentage' => $percentagem, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $codigo;
    }

    private function verbaCom(string $taxa, string $tipo): string
    {
        $numero = (string) random_int(100000, 999999);

        DB::table('agt_is_verbas')->insert([
            'verba_no' => $numero, 'description' => 'Teste Selo',
            'rate' => $taxa, 'rate_type' => $tipo, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $numero;
    }

    private function criar(array $linha): SalesInvoice
    {
        $r = $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', [
            'doc_type'   => 'FT',
            'local_uuid' => 'doc_' . uniqid(),
            'items'      => [array_merge([
                'product_name' => 'Artigo',
                'quantity'     => 1,
                'unit_price'   => 1000,
            ], $linha)],
        ])->assertSuccessful()->json();

        return SalesInvoice::withoutGlobalScopes()->findOrFail($r['id']);
    }

    public function test_o_iec_e_gravado_na_linha(): void
    {
        $doc = $this->criar(['iec_pautal' => $this->pautalCom(10)]);
        $linha = $doc->items()->first();

        $imposto = LineTax::where('line_type', SalesInvoiceItem::class)
            ->where('line_id', $linha->id)->where('tax_type', 'IEC')->first();

        $this->assertNotNull($imposto, 'o IEC tinha de ficar em invoicing_line_taxes');
        $this->assertEquals(100, (float) $imposto->tax_amount, '10% de 1000');
    }

    public function test_o_selo_de_valor_fixo_nao_depende_do_preco(): void
    {
        $doc = $this->criar(['is_verba' => $this->verbaCom('AOA 250', 'FIXED')]);
        $linha = $doc->items()->first();

        $selo = LineTax::where('line_type', SalesInvoiceItem::class)
            ->where('line_id', $linha->id)->where('tax_type', 'IS')->first();

        $this->assertNotNull($selo);
        $this->assertEquals(250, (float) $selo->tax_amount);
    }

    public function test_o_iva_incide_sobre_o_liquido_MAIS_o_iec(): void
    {
        $semIec = $this->criar([]);
        $comIec = $this->criar(['iec_pautal' => $this->pautalCom(10)]);

        // Sem isto netTotal + taxPayable != grossTotal e a AGT recusa.
        $this->assertGreaterThan(
            (float) $semIec->tax_amount,
            (float) $comIec->tax_amount,
            'o IEC tinha de entrar na base do IVA'
        );
    }

    public function test_o_selo_NAO_entra_na_base_do_iva(): void
    {
        $semSelo = $this->criar([]);
        $comSelo = $this->criar(['is_verba' => $this->verbaCom('AOA 250', 'FIXED')]);

        $this->assertEquals((float) $semSelo->tax_amount, (float) $comSelo->tax_amount);
    }

    public function test_os_impostos_extra_acrescem_ao_total(): void
    {
        $sem = $this->criar([]);
        $com = $this->criar(['is_verba' => $this->verbaCom('AOA 250', 'FIXED')]);

        $this->assertEquals((float) $sem->total + 250, (float) $com->total);
    }

    public function test_um_pautal_desconhecido_nao_inventa_imposto(): void
    {
        $doc = $this->criar(['iec_pautal' => 'NAO-EXISTE-XYZ']);
        $linha = $doc->items()->first();

        $this->assertSame(0, LineTax::where('line_type', SalesInvoiceItem::class)
            ->where('line_id', $linha->id)->count());
    }
}
