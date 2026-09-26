<?php

namespace Tests\Feature;

use App\Helpers\InvoiceCalculationHelper;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Services\AGT\AGTPayloadBuilder;
use App\Services\AGT\DocumentMapper;
use App\Services\Invoicing\DescontoDoDocumento;
use App\Services\Invoicing\EmissorDeNotas;
use App\Services\Invoicing\GeradorDeSaft;
use Tests\TenantTestCase;

/**
 * O DESCONTO DO DOCUMENTO chega às linhas — na AGT, na nota de crédito e no SAF-T.
 *
 * PORQUE EXISTE. O balcão grava o desconto só no documento: as linhas ficam ao
 * preço cheio e o net_total descontado. A AGT recusou duas FR da Tecstore com
 * E23 («netTotal 293.456,5 não corresponde à soma das linhas 304.100»), e a
 * nota de crédito da FR/000060 (289.900 com 15.900 de desconto) não se
 * conseguia emitir: anulava 289.900 numa factura de 274.000, e o travão do E43
 * parava-a.
 */
class DescontoDoDocumentoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        InvoicingSeries::create([
            'tenant_id' => $this->tenant->id,
            'series_code' => 'NC',
            'name' => 'NC (ensaio)',
            'document_type' => 'credit_note',
            'agt_environment' => 'sandbox',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Uma FR como o balcão a grava (PosSaleService): linhas ao preço cheio,
     * sem desconto; o desconto e o líquido só no documento.
     *
     * @param  array<int, array{0: float, 1: float, 2: float}>  $linhas  [preço, quantidade, taxa]
     */
    private function frDoBalcao(array $linhas, float $desconto): SalesInvoice
    {
        $carrinho = collect($linhas)->map(fn ($l) => (object) [
            'price' => $l[0], 'quantity' => $l[1],
            'attributes' => ['discount_percent' => 0, 'tax_rate' => $l[2]],
        ]);
        $calc = InvoiceCalculationHelper::calculateTotals($carrinho, $desconto, 0, 0, false);

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_type' => 'FR',
            'invoice_number' => 'FR ENSAIO/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => $calc['subtotal'],
            'net_total' => $calc['incidencia_iva'],
            'tax_amount' => $calc['tax_amount'],
            'tax_payable' => $calc['tax_amount'],
            'gross_total' => $calc['incidencia_iva'] + $calc['tax_amount'],
            'discount_amount' => $calc['desconto_comercial_total'],
            'discount_commercial' => $desconto,
            'total' => $calc['total'],
            'paid_amount' => $calc['total'],
            'created_by' => $this->user->id,
        ]);

        foreach ($linhas as $i => [$preco, $quantidade, $taxa]) {
            $bruto = round($preco * $quantidade, 2);

            SalesInvoiceItem::create([
                'sales_invoice_id' => $f->id,
                'product_name' => "Artigo {$i}",
                'description' => "Artigo {$i}",
                'quantity' => $quantidade,
                'unit' => 'UN',
                'unit_price' => $preco,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'subtotal' => $bruto,
                'tax_rate' => $taxa,
                'tax_amount' => round($bruto * $taxa / 100, 2),
                'total' => $bruto + round($bruto * $taxa / 100, 2),
                'order' => $i + 1,
                'tax_code' => $taxa > 0 ? 'NOR' : 'ISE',
                'tax_country_region' => 'AO',
                'tax_exemption_code' => $taxa > 0 ? null : 'M11',
            ]);
        }

        return $f->fresh(['items']);
    }

    private function somaDasLinhas(array $doc, string $lado = 'creditAmount'): float
    {
        return round(array_sum(array_column($doc['lines'], $lado)), 2);
    }

    /* ─── AGT ─────────────────────────────────────────────────────────── */

    public function test_a_fr_do_balcao_com_desconto_vai_a_agt_com_as_linhas_descontadas(): void
    {
        // A FR/000060 da Tecstore: 289.900, isenta, 15.900 de desconto.
        $f = $this->frDoBalcao([[289900, 1, 0]], 15900);

        $doc = (new DocumentMapper())->map($f);
        $linha = $doc['lines'][0];

        $this->assertSame(274000.0, $doc['documentTotals']['netTotal']);
        $this->assertSame(274000.0, $linha['creditAmount'], 'a linha leva o líquido, não o preço cheio');
        $this->assertSame(289900.0, $linha['unitPrice'], 'unitPrice é o preço SEM descontos (DS.120)');
        $this->assertSame(274000.0, $linha['unitPriceBase'], 'unitPriceBase é o preço já descontado (DS.120, E21)');
        $this->assertSame(15900.0, $linha['settlementAmount'], 'settlementAmount é o desconto da linha');
    }

    public function test_com_varias_linhas_e_iva_a_soma_bate_ao_centimo_com_o_net_total(): void
    {
        // A FR 000099 recusada: 172.500 + 131.600, com 10.643,50 de desconto.
        // Aqui a 14%, para o imposto também entrar na conta.
        $f = $this->frDoBalcao([[172500, 1, 14], [131600, 1, 14]], 10643.5);

        $doc = (new DocumentMapper())->map($f);

        $this->assertSame((float) round($f->net_total, 2), $doc['documentTotals']['netTotal']);
        $this->assertSame($doc['documentTotals']['netTotal'], $this->somaDasLinhas($doc), 'E23: as linhas somam o netTotal');
        $this->assertEqualsWithDelta(10643.5, array_sum(array_column($doc['lines'], 'settlementAmount')), 0.001);

        foreach ($doc['lines'] as $linha) {
            // O imposto de cada linha sai do líquido DESCONTADO, por CEIL (E70).
            $this->assertSame(AGTPayloadBuilder::ceilCents($linha['creditAmount'] * 0.14), $linha['taxes'][0]['taxContribution']);
            $this->assertEqualsWithDelta($linha['creditAmount'], round($linha['quantity'] * $linha['unitPriceBase'], 2), 0.001, 'E21');
        }

        $imposto = round(array_sum(array_map(fn ($l) => $l['taxes'][0]['taxContribution'], $doc['lines'])), 2);
        $this->assertSame($imposto, $doc['documentTotals']['taxPayable']);
        $this->assertSame(round($doc['documentTotals']['netTotal'] + $imposto, 2), $doc['documentTotals']['grossTotal']);
    }

    public function test_o_preco_descontado_que_nao_se_divide_certo_vai_com_quatro_casas(): void
    {
        // 3 × 100, 0,01 de desconto: líquido 299,99, que não se divide por 3.
        $f = $this->frDoBalcao([[100, 3, 0]], 0.01);

        $linha = (new DocumentMapper())->map($f)['lines'][0];

        $this->assertSame(299.99, $linha['creditAmount']);
        $this->assertSame(99.9967, $linha['unitPriceBase']);
        $this->assertSame(299.99, round($linha['quantity'] * $linha['unitPriceBase'], 2), 'E21: quantidade × unitPriceBase = creditAmount');

        $construtor = (new \ReflectionClass(AGTPayloadBuilder::class))->newInstanceWithoutConstructor();
        $normalizada = (new \ReflectionMethod(AGTPayloadBuilder::class, 'normalizeLine'))->invoke($construtor, $linha);
        $this->assertSame(99.9967, $normalizada['unitPriceBase'], 'o construtor não a corta a duas casas');
    }

    public function test_sem_desconto_a_linha_sai_como_sempre_saiu(): void
    {
        $f = $this->frDoBalcao([[1000, 2, 14]], 0);

        $linha = (new DocumentMapper())->map($f)['lines'][0];

        $this->assertSame(2000.0, $linha['creditAmount']);
        $this->assertSame(1000.0, $linha['unitPrice']);
        $this->assertSame(1000.0, $linha['unitPriceBase']);
        $this->assertSame(0.0, $linha['settlementAmount']);
    }

    public function test_o_desconto_nao_inflaciona_linhas_que_ja_somam_menos(): void
    {
        $f = $this->frDoBalcao([[1000, 1, 0]], 0);
        $f->net_total = 1200; // um documento que declara mais do que as linhas
        $valores = DescontoDoDocumento::repartir($f, $f->items);

        $this->assertSame(1000.0, $valores[0]['liquido']);
        $this->assertSame(0.0, $valores[0]['desconto']);
    }

    public function test_so_se_reparte_o_desconto_que_o_documento_declara(): void
    {
        // Facturas antigas têm net_total a 0 (o valor por omissão) e linhas
        // cheias: isso não é um desconto de 100%.
        $f = $this->frDoBalcao([[1000, 1, 0]], 0);
        $f->net_total = 0;
        $this->assertSame(1000.0, DescontoDoDocumento::repartir($f, $f->items)[0]['liquido']);

        // Nem uma diferença maior do que o desconto gravado.
        $f->net_total = 700;
        $f->discount_amount = 100;
        $f->discount_commercial = 100;
        $this->assertSame(1000.0, DescontoDoDocumento::repartir($f, $f->items)[0]['liquido']);

        // O que o documento declara, sim.
        $f->net_total = 800;
        $this->assertSame(800.0, DescontoDoDocumento::repartir($f, $f->items)[0]['liquido']);
    }

    /* ─── Nota de crédito ─────────────────────────────────────────────── */

    public function test_anula_a_fr_com_desconto_pelo_que_ela_valeu(): void
    {
        $f = $this->frDoBalcao([[289900, 1, 0]], 15900);
        $emissor = app(EmissorDeNotas::class);

        $linhas = $emissor->linhasDaFactura($f);
        $this->assertEqualsWithDelta(15900 / 289900 * 100, $linhas[0]->attributes['discount_percent'], 1e-9,
            'a linha da nota leva o desconto da factura');

        // Antes: «Esta nota anula 289.900,00, mas a factura só tem 274.000,00 por anular».
        $nc = $emissor->emitirCredito([
            'client_id' => $f->client_id,
            'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'return',
            'type' => 'total',
        ], $linhas)['nota']->fresh(['items']);

        $this->assertEqualsWithDelta(274000, (float) $nc->total, 0.001);
        $this->assertEqualsWithDelta(274000, (float) $nc->net_total, 0.001, 'a base é o líquido, não o bruto');
        $this->assertEqualsWithDelta(274000, (float) $nc->gross_total, 0.001, 'e é o que o hash assina');
        $this->assertEqualsWithDelta(274000, (float) $nc->items[0]->debit_amount, 0.001);
        $this->assertTrue($f->fresh()->jaTotalmenteCreditada());

        // E a AGT recebe-a com as linhas a somar o netTotal.
        $doc = (new DocumentMapper())->map($nc);
        $this->assertSame(274000.0, $doc['documentTotals']['netTotal']);
        $this->assertSame(274000.0, $this->somaDasLinhas($doc, 'debitAmount'));
        $this->assertSame(289900.0, $doc['lines'][0]['unitPrice']);
        $this->assertSame(274000.0, $doc['lines'][0]['unitPriceBase']);
        $this->assertSame(15900.0, $doc['lines'][0]['settlementAmount']);
    }

    public function test_anula_a_fr_com_desconto_e_iva_em_varias_linhas(): void
    {
        $f = $this->frDoBalcao([[172500, 1, 14], [131600, 1, 14]], 10643.5);
        $emissor = app(EmissorDeNotas::class);

        $nc = $emissor->emitirCredito([
            'client_id' => $f->client_id,
            'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'return',
            'type' => 'total',
        ], $emissor->linhasDaFactura($f))['nota']->fresh(['items']);

        $this->assertEqualsWithDelta((float) $f->net_total, (float) $nc->net_total, 0.001);
        $this->assertEqualsWithDelta((float) $f->total, (float) $nc->total, 0.02);

        // As linhas da nota repetem as da factura tal como foram à AGT.
        $daFactura = (new DocumentMapper())->map($f)['lines'];
        $daNota = (new DocumentMapper())->map($nc)['lines'];

        foreach ($daFactura as $i => $linha) {
            $this->assertSame($linha['creditAmount'], $daNota[$i]['debitAmount']);
            $this->assertSame($linha['taxes'][0]['taxContribution'], $daNota[$i]['taxes'][0]['taxContribution']);
        }
    }

    public function test_a_anulacao_parcial_desconta_na_mesma_proporcao(): void
    {
        // 2 × 1000 isentos, 200 de desconto: cada unidade vale 900.
        $f = $this->frDoBalcao([[1000, 2, 0]], 200);
        $emissor = app(EmissorDeNotas::class);

        $linhas = $emissor->linhasDaFactura($f)->map(function ($l) {
            $l->quantity = 1;

            return $l;
        });

        $nc = $emissor->emitirCredito([
            'client_id' => $f->client_id,
            'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'return',
            'type' => 'partial',
        ], $linhas)['nota'];

        $this->assertEqualsWithDelta(900, (float) $nc->total, 0.001);
        $this->assertEqualsWithDelta(900, $f->fresh()->porCreditar(), 0.001);
    }

    public function test_a_nota_de_uma_linha_com_desconto_proprio_tem_a_base_liquida(): void
    {
        // Factura normal: 2 × 1000 a 10% de desconto na linha (líquido 1800).
        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ENSAIO/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'subtotal' => 2000,
            'net_total' => 1800,
            'tax_amount' => 0,
            'gross_total' => 1800,
            'total' => 1800,
            'created_by' => $this->user->id,
        ]);
        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_name' => 'Serviço', 'description' => 'Serviço',
            'quantity' => 2, 'unit_price' => 1000, 'discount_percent' => 10, 'subtotal' => 2000,
            'tax_rate' => 0, 'tax_code' => 'ISE', 'tax_exemption_code' => 'M11', 'order' => 1,
        ]);
        $f = $f->fresh(['items']);
        $emissor = app(EmissorDeNotas::class);

        $nc = $emissor->emitirCredito([
            'client_id' => $f->client_id,
            'invoice_id' => $f->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'return',
            'type' => 'total',
        ], $emissor->linhasDaFactura($f))['nota']->fresh(['items']);

        // Era o bruto (2000): o total e o hash saíam acima das linhas.
        $this->assertEqualsWithDelta(1800, (float) $nc->net_total, 0.001);
        $this->assertEqualsWithDelta(1800, (float) $nc->total, 0.001);

        $doc = (new DocumentMapper())->map($nc);
        $this->assertSame(1800.0, $doc['documentTotals']['netTotal']);
        $this->assertSame(1800.0, $this->somaDasLinhas($doc, 'debitAmount'));
    }

    /* ─── SAF-T ───────────────────────────────────────────────────────── */

    public function test_o_saft_escreve_as_linhas_descontadas(): void
    {
        $f = $this->frDoBalcao([[289900, 1, 0]], 15900);
        $dia = now()->toDateString();

        $xml = new \SimpleXMLElement((new GeradorDeSaft($this->tenant->id, $dia, $dia))->xml());
        $xml->registerXPathNamespace('s', $xml->getNamespaces()[''] ?? '');

        $factura = collect($xml->xpath('//s:SalesInvoices/s:Invoice'))
            ->first(fn ($n) => (string) $n->InvoiceNo === $f->invoice_number);

        $this->assertNotNull($factura, 'a factura está no SAF-T');
        $this->assertSame('274000.00', (string) $factura->Line[0]->CreditAmount);
        $this->assertSame('274000.00', (string) $factura->Line[0]->UnitPrice);
        $this->assertSame('15900.00', (string) $factura->Line[0]->SettlementAmount);
        $this->assertSame('274000.00', (string) $factura->DocumentTotals->NetTotal);
    }
}
