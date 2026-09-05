<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Services\Invoicing\EmissorDeNotas;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * O EMISSOR DE NOTAS, A EMITIR A SÉRIO.
 *
 * Os outros ensaios das notas lêem código ou contam saldos. Este passa uma
 * factura pelo serviço e vê o que sai: número da série, hash encadeado, linhas
 * com o imposto herdado da linha original, retenção revertida — e o E43 a
 * travar ANTES de a nota nascer.
 *
 * É o ensaio que garante que tirar ~250 linhas de dentro de cada componente
 * Livewire não mudou o que a AGT recebe.
 */
class EmissorDeNotasTest extends TenantTestCase
{
    private EmissorDeNotas $emissor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
        $this->emissor = app(EmissorDeNotas::class);

        foreach ([['NC', 'credit_note'], ['ND', 'debit_note']] as [$codigo, $tipo]) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id,
                'series_code' => $codigo,
                'name' => "{$codigo} (ensaio)",
                'document_type' => $tipo,
                'agt_environment' => 'sandbox',
                'is_default' => true,
                'is_active' => true,
            ]);
        }
    }

    private function artigo(float $preco, bool $comIva): Product
    {
        $categoria = Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        $taxa = $comIva ? Tax::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']
        ) : null;

        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo ' . uniqid(),
            'type' => 'produto',
            'price' => $preco,
            'unit' => 'un',
            'category_id' => $categoria->id,
            'tax_type' => $comIva ? 'iva' : 'isento',
            'tax_rate_id' => $taxa?->id,
            'exemption_reason' => $comIva ? null : 'M99',
            'is_active' => true,
        ]);
    }

    /** Uma factura com duas linhas: 2 × 1000 a 14% e 3 × 500 isentos. */
    private function factura(): SalesInvoice
    {
        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ENSAIO/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'subtotal' => 3500,
            'tax_amount' => 280,
            'total' => 3780,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ]);

        $comIva = $this->artigo(1000, true);
        $isento = $this->artigo(500, false);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_id' => $comIva->id, 'product_name' => $comIva->name,
            'description' => $comIva->name, 'quantity' => 2, 'unit_price' => 1000,
            'subtotal' => 2000, 'tax_rate' => 14, 'tax_amount' => 280, 'total' => 2280,
            'tax_code' => 'NOR', 'tax_country_region' => 'AO-CAB', 'order' => 1,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_id' => $isento->id, 'product_name' => $isento->name,
            'description' => $isento->name, 'quantity' => 3, 'unit_price' => 500,
            'subtotal' => 1500, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 1500,
            'tax_code' => 'ISE', 'tax_exemption_code' => 'M99', 'order' => 2,
        ]);

        return $f->fresh(['items']);
    }

    /* ─── Nota de crédito ─────────────────────────────────────────────── */

    /** @test */
    public function emite_uma_nota_de_credito_total_com_numero_e_hash(): void
    {
        $f = $this->factura();

        $r = $this->emissor->emitirCredito([
            'client_id' => $f->client_id,
            'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'return',
            'type' => 'total',
        ], $this->emissor->linhasDaFactura($f));

        $nc = $r['nota']->fresh(['items']);

        $this->assertNotEmpty($nc->credit_note_number, 'numera-se pela série');
        $this->assertNotEmpty($nc->saft_hash, 'e sai com hash — sem ele fica fora da cadeia');
        $this->assertSame('Anulação', $nc->reason_text, 'anulação total leva a expressão do Art. 12º');
        $this->assertEqualsWithDelta(3780, $nc->gross_total, 0.01, 'anula a factura inteira');
        $this->assertSame(2, $nc->items->count());

        // A factura fica marcada e sem nada por anular.
        $this->assertTrue($f->fresh()->jaTotalmenteCreditada());
    }

    /**
     * A REGIÃO, O CÓDIGO E O MOTIVO VÊM DA LINHA ORIGINAL.
     *
     * Fixá-los fazia o crédito de uma factura de Cabinda sair como AO.
     *
     * @test
     */
    public function as_linhas_herdam_o_imposto_da_linha_original(): void
    {
        $f = $this->factura();

        $nc = $this->emissor->emitirCredito([
            'client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'total',
        ], $this->emissor->linhasDaFactura($f))['nota']->fresh(['items']);

        $comIva = $nc->items->firstWhere('tax_rate', 14);
        $isenta = $nc->items->firstWhere('tax_rate', 0);

        $this->assertSame('AO-CAB', $comIva->tax_country_region, 'Cabinda continua Cabinda');
        $this->assertSame('NOR', $comIva->tax_code);
        $this->assertNull($comIva->tax_exemption_code);

        $this->assertSame('ISE', $isenta->tax_code);
        $this->assertSame('M99', $isenta->tax_exemption_code, 'linha isenta leva motivo — a AGT rejeita sem código');
        $this->assertNotEmpty($isenta->tax_exemption_reason);

        // referenceInfo: a linha diz de que factura e de que linha vem.
        $this->assertSame($f->invoice_number, $comIva->reference_invoice_no);
        $this->assertSame(1, (int) $comIva->reference_item_line_no);
        $this->assertSame(2, (int) $isenta->reference_item_line_no);
    }

    /**
     * O E43 TRAVA ANTES DE A NOTA NASCER — pelo total.
     *
     * @test
     */
    public function nao_nasce_uma_nota_que_anule_mais_do_que_a_factura_tem(): void
    {
        $f = $this->factura();

        // Uma nota parcial primeiro: 2000 dos 3780.
        $linhas = $this->emissor->linhasDaFactura($f)->filter(fn ($l) => $l->attributes['tax_rate'] == 14)->values();

        $this->emissor->emitirCredito([
            'client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'partial',
        ], $linhas);

        $antes = DB::table('invoicing_credit_notes')->count();

        // Agora a factura inteira outra vez: 3780 > o que resta (1500).
        try {
            $this->emissor->emitirCredito([
                'client_id' => $f->client_id, 'invoice_id' => $f->id,
                'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'total',
            ], $this->emissor->linhasDaFactura($f));

            $this->fail('tinha de travar');
        } catch (DomainException $e) {
            $this->assertStringContainsString('por anular', $e->getMessage());
        }

        $this->assertSame($antes, DB::table('invoicing_credit_notes')->count(),
            'e não ficou nenhuma nota a meio — o travão é ANTES de nascer');
    }

    /**
     * E POR LINHA: bater certo no total não chega.
     *
     * @test
     */
    public function nao_nasce_uma_nota_que_credite_mais_unidades_do_que_a_linha_teve(): void
    {
        $f = $this->factura();

        $linhas = $this->emissor->linhasDaFactura($f)->map(function ($l) {
            // 69 da linha que teve 2 — o caso da NC4226S46906N/000002.
            if ($l->attributes['tax_rate'] == 14) {
                $l->quantity = 69;
            }

            return $l;
        });

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('quantidades por anular');

        $this->emissor->emitirCredito([
            'client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'partial',
        ], $linhas);
    }

    /** A retenção na fonte da factura é revertida na nota, tal e qual. @test */
    public function a_nota_de_credito_reverte_a_retencao_da_factura(): void
    {
        $f = $this->factura();

        DB::table('invoicing_withholding_taxes')->insert([
            'tenant_id' => $this->tenant->id,
            'document_type' => SalesInvoice::class,
            'document_id' => $f->id,
            'withholding_tax_type' => 'IRT',
            'withholding_tax_description' => 'IRT',
            'withholding_tax_percentage' => 6.5,
            'withholding_tax_amount' => 227.50,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $nc = $this->emissor->emitirCredito([
            'client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'total',
        ], $this->emissor->linhasDaFactura($f))['nota'];

        $ret = DB::table('invoicing_withholding_taxes')
            ->where('document_type', get_class($nc))->where('document_id', $nc->id)->first();

        $this->assertNotNull($ret, 'sem a withholdingTaxList o crédito fica incompleto');
        $this->assertEqualsWithDelta(227.50, $ret->withholding_tax_amount, 0.01);
    }

    /* ─── Nota de débito ──────────────────────────────────────────────── */

    /** @test */
    public function emite_uma_nota_de_debito_com_a_base_liquida_das_linhas(): void
    {
        $f = $this->factura();

        $linhas = $this->emissor->linhasDaFactura($f)->map(function ($l) {
            $l->quantity = 1;
            $l->attributes['discount_percent'] = 10;

            return $l;
        });

        $nd = $this->emissor->emitirDebito([
            'client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'correction',
        ], $linhas)['nota']->fresh(['items']);

        $this->assertNotEmpty($nd->debit_note_number);
        $this->assertNotEmpty($nd->saft_hash, 'a ND entra na cadeia como a NC');

        // Base = soma das bases das LINHAS, já com o desconto: (1000 + 500) − 10%.
        $this->assertEqualsWithDelta(1350, $nd->net_total, 0.01,
            'o net_total era o bruto e o cliente era debitado a mais no valor do desconto');

        // A ND acresce à dívida: creditAmount, não debitAmount.
        $linha = $nd->items->first();
        $this->assertGreaterThan(0, (float) $linha->credit_amount);
        $this->assertSame(0, (int) $linha->debit_amount);
    }

    /**
     * A RETENÇÃO DE UMA ND É PROPORCIONAL À SUA BASE.
     *
     * Copiar o valor da factura tal e qual fazia uma ND de 10.000 sobre uma
     * factura de 1.000.000 declarar 65.000 de retenção.
     *
     * @test
     */
    public function a_retencao_da_nota_de_debito_e_proporcional(): void
    {
        $f = $this->factura();

        DB::table('invoicing_withholding_taxes')->insert([
            'tenant_id' => $this->tenant->id,
            'document_type' => SalesInvoice::class,
            'document_id' => $f->id,
            'withholding_tax_type' => 'IRT',
            'withholding_tax_description' => 'IRT',
            'withholding_tax_percentage' => 6.5,
            'withholding_tax_amount' => 227.50,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Só a linha isenta, 1 unidade: base 500.
        $linhas = $this->emissor->linhasDaFactura($f)
            ->filter(fn ($l) => $l->attributes['tax_rate'] == 0)
            ->map(function ($l) { $l->quantity = 1; return $l; })
            ->values();

        $nd = $this->emissor->emitirDebito([
            'client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'correction',
        ], $linhas)['nota'];

        $ret = DB::table('invoicing_withholding_taxes')
            ->where('document_type', get_class($nd))->where('document_id', $nd->id)->first();

        $this->assertEqualsWithDelta(32.50, $ret->withholding_tax_amount, 0.01, '6,5% de 500, não os 227,50 da factura');
    }

    /** O hash encadeia na nota anterior do mesmo tipo. @test */
    public function o_hash_encadeia_na_nota_anterior(): void
    {
        $f1 = $this->factura();
        $f2 = $this->factura();

        $dados = fn ($f) => ['client_id' => $f->client_id, 'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'total'];

        $primeira = $this->emissor->emitirCredito($dados($f1), $this->emissor->linhasDaFactura($f1))['nota'];
        $segunda = $this->emissor->emitirCredito($dados($f2), $this->emissor->linhasDaFactura($f2))['nota'];

        $this->assertSame($primeira->fresh()->saft_hash, $segunda->fresh()->hash_previous,
            'a segunda aponta para o hash da primeira — é isso a cadeia');
    }
}
