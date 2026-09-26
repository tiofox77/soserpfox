<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Product;
use App\Services\Invoicing\EmissorDeNotas;
use App\Services\POS\ProdutosDoTurno;
use Tests\TenantTestCase;

/**
 * OS DOCUMENTOS DO ECRÃ «DOCUMENTOS» NO FECHO DE TURNO (26/09/2026).
 *
 * Quem tem o turno aberto no POS também emite pelos Documentos. A FR A4 punha
 * o dinheiro na caixa do operador e o turno não sabia dele: o fecho acusava
 * uma sobra. Agora, com a opção da empresa ligada (é o valor por omissão), a
 * FR conta pela forma de pagamento, e a FT, a ND e a NC sem devolução saem a
 * prazo — no fecho, fora da gaveta. Desligada, nada disto entra no turno.
 */
class DocumentosNoFechoDeTurnoTest extends TenantTestCase
{
    private const FACTURA = '/api/v1/invoicing/react/factura';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.invoices.create');

        foreach ([['NC', 'credit_note'], ['ND', 'debit_note']] as [$codigo, $tipo]) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => $codigo, 'name' => "{$codigo} (ensaio)",
                'document_type' => $tipo, 'agt_environment' => 'sandbox', 'is_default' => true, 'is_active' => true,
            ]);
        }
    }

    private function turno(): PosShift
    {
        return PosShift::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'shift_number' => 'T' . random_int(10000, 99999), 'opened_at' => now(),
            'opening_balance' => 0, 'status' => 'open',
        ]);
    }

    private function opcao(bool $ligada): void
    {
        InvoicingSettings::forTenant($this->tenant->id)->update(['turno_inclui_documentos' => $ligada]);
    }

    /** Emite pela API dos Documentos: 2 × 1000, isento. */
    private function emitir(string $tipo, array $por = []): SalesInvoice
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => 'isento', 'exemption_reason' => 'M99',
            'manage_stock' => false, 'is_active' => true,
        ]);

        $r = $this->postJson(self::FACTURA, array_merge([
            'client_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => $tipo,
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 2, 'price' => 1000]],
        ], $tipo === 'FR' ? ['payment_method' => 'cash'] : [], $por))->assertCreated();

        return SalesInvoice::findOrFail($r->json('id'));
    }

    public function test_a_fr_a4_conta_na_gaveta_do_turno(): void
    {
        $turno = $this->turno();

        $fr = $this->emitir('FR');
        $turno->refresh();

        $movimento = $turno->transactions()->where('reference_type', SalesInvoice::class)->where('reference_id', $fr->id)->first();
        $this->assertNotNull($movimento, 'a FR A4 entrou no turno de quem a emitiu');
        $this->assertSame('invoice', $movimento->type);
        $this->assertEqualsWithDelta(2000, (float) $turno->cash_sales, 0.01, 'em dinheiro, conta no balde do numerário');
        $this->assertEqualsWithDelta(2000, $turno->dinheiroEsperado(), 0.01, 'e no esperado da gaveta');

        $documentos = ProdutosDoTurno::de($turno)['documentos'];
        $this->assertCount(1, $documentos);
        $this->assertFalse($documentos[0]['a_prazo']);
    }

    public function test_a_ft_sai_a_prazo_sem_mexer_na_gaveta(): void
    {
        $turno = $this->turno();

        $ft = $this->emitir('FT');
        $turno->refresh();

        $movimento = $turno->transactions()->where('reference_id', $ft->id)->first();
        $this->assertSame('a_prazo', $movimento->type);
        $this->assertEqualsWithDelta(0, (float) $turno->total_sales, 0.01, 'não é dinheiro recebido');
        $this->assertEqualsWithDelta(0, $turno->dinheiroEsperado(), 0.01, 'e não mexe na gaveta');
        $this->assertSame(['quantos' => 1, 'valor' => 2000.0], $turno->documentosAPrazo());

        $r = ProdutosDoTurno::de($turno);
        $this->assertTrue($r['documentos'][0]['a_prazo']);
        $this->assertSame('A prazo', $r['documentos'][0]['meio']);
        $this->assertEqualsWithDelta(2000, $r['totais']['a_prazo'], 0.01);
        $this->assertCount(1, $r['produtos'], 'os artigos da FT contam no fecho com produtos');
    }

    public function test_a_nd_e_a_nc_de_uma_factura_por_pagar_saem_a_prazo(): void
    {
        $turno = $this->turno();
        $emissor = app(EmissorDeNotas::class);

        $ft = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ENSAIO/' . random_int(1000, 9999), 'invoice_date' => now()->toDateString(),
            'status' => 'sent', 'subtotal' => 1000, 'net_total' => 1000, 'total' => 1000, 'paid_amount' => 0,
            'created_by' => $this->user->id,
        ]);
        SalesInvoiceItem::create([
            'sales_invoice_id' => $ft->id, 'product_name' => 'Serviço', 'description' => 'Serviço',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000, 'tax_rate' => 0,
            'tax_code' => 'ISE', 'tax_exemption_code' => 'M99', 'order' => 1,
        ]);
        $ft = $ft->fresh(['items']);

        $dados = ['client_id' => $ft->client_id, 'invoice_id' => $ft->id, 'issue_date' => now()->toDateString()];

        $nd = $emissor->emitirDebito($dados + ['reason' => 'correction'], $emissor->linhasDaFactura($ft))['nota'];
        $nc = $emissor->emitirCredito($dados + ['reason' => 'return', 'type' => 'total'], $emissor->linhasDaFactura($ft))['nota'];

        $turno->refresh();
        $aPrazo = $turno->transactions()->where('type', 'a_prazo')->get()->keyBy('reference_type');

        $this->assertEqualsWithDelta((float) $nd->total, (float) $aPrazo[get_class($nd)]->amount, 0.01);
        $this->assertEqualsWithDelta(-(float) $nc->total, (float) $aPrazo[get_class($nc)]->amount, 0.01,
            'a NC de uma factura que ninguém pagou não devolve dinheiro: sai a prazo, negativa');
        $this->assertEqualsWithDelta(0, $turno->dinheiroEsperado(), 0.01);

        $tipos = array_column(ProdutosDoTurno::de($turno)['documentos'], 'tipo');
        $this->assertContains('debito', $tipos);
        $this->assertContains('nota', $tipos);
    }

    public function test_desligada_nada_disto_entra_no_turno(): void
    {
        $this->opcao(false);
        $turno = $this->turno();

        $fr = $this->emitir('FR');
        $this->emitir('FT');

        // A devolução da FR paga também fica só na tesouraria.
        $emissor = app(EmissorDeNotas::class);
        $emissor->emitirCredito([
            'client_id' => $fr->client_id, 'invoice_id' => $fr->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => 'total',
        ], $emissor->linhasDaFactura($fr->fresh(['items'])));

        $this->assertSame(0, $turno->transactions()->count(), 'com a opção desligada o fecho é só do POS');
    }

    public function test_sem_turno_aberto_nao_ha_onde_entrar(): void
    {
        $this->emitir('FR');

        $this->assertSame(0, PosShift::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_opcao_grava_nas_definicoes_e_nasce_ligada(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');
        $raiz = '/api/v1/invoicing/react/definicoes';

        $definicoes = $this->getJson($raiz)->assertOk()->json('definicoes');
        $this->assertTrue($definicoes['turno_inclui_documentos'], 'nunca configurada é ligada');

        $this->putJson($raiz, array_merge($definicoes, ['turno_inclui_documentos' => false]))->assertOk();

        $this->assertFalse((bool) InvoicingSettings::forTenant($this->tenant->id)->fresh()->turno_inclui_documentos);
    }
}
