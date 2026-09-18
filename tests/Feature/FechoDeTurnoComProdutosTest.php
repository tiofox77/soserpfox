<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\User;
use App\Services\Invoicing\EmissorDeNotas;
use App\Services\POS\ProdutosDoTurno;
use Illuminate\Support\Collection;
use Tests\TenantTestCase;

/**
 * O FECHO COM PRODUTOS (16/09/2026): ao fechar o turno escolhe-se o resumido
 * ou o com produtos, e o segundo mostra o que se vendeu artigo a artigo, os
 * totais e os documentos — no ecrã, no talão e no PDF.
 */
class FechoDeTurnoComProdutosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private Product $pao;

    private Product $cafe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'NC', 'name' => 'NC (ensaio)',
            'document_type' => 'credit_note', 'agt_environment' => 'sandbox',
            'is_default' => true, 'is_active' => true,
        ]);

        $this->pao = $this->artigo('Pão de forma', 'PAO-01');
        $this->cafe = $this->artigo('Café expresso', 'CAF-01');
    }

    public function test_junta_as_vendas_do_turno_artigo_a_artigo_com_devolucoes_anuladas_e_desconto(): void
    {
        $turno = $this->turnoAberto();

        // Duas vendas de pão; a segunda paga em dois meios (dois movimentos, uma venda).
        $this->venda($turno, [[$this->pao, 2, 1000]], ['cash']);
        $dividida = $this->venda($turno, [[$this->pao, 1, 1000], [$this->cafe, 3, 500]], ['cash', 'tpa']);
        // Uma venda com desconto no documento.
        $this->venda($turno, [[$this->cafe, 2, 500]], ['cash'], desconto: 100);
        // Uma anulada: fica nos documentos, não nos artigos.
        $anulada = $this->venda($turno, [[$this->pao, 10, 1000]], ['cash']);
        $anulada->forceFill(['status' => 'cancelled'])->save();
        // E uma venda de outro turno que não pode entrar.
        $this->factura([[$this->pao, 50, 1000]]);

        // Devolve-se a venda dividida inteira.
        $this->creditar($dividida);

        $r = ProdutosDoTurno::de($turno->fresh());
        $porNome = collect($r['produtos'])->keyBy('nome');

        $this->assertEqualsCanonicalizing(['Pão de forma', 'Café expresso'], $porNome->keys()->all());

        $pao = $porNome['Pão de forma'];
        $this->assertEqualsWithDelta(3, $pao['quantidade'], 0.001, '2 + 1 — a anulada não conta');
        $this->assertEqualsWithDelta(1, $pao['devolvida'], 0.001);
        $this->assertEqualsWithDelta(2, $pao['liquida'], 0.001);
        $this->assertEqualsWithDelta(3420, $pao['total'], 0.01, '3 × 1000 com 14%');
        $this->assertEqualsWithDelta(1140, $pao['devolvido'], 0.01);
        $this->assertEqualsWithDelta(1140, $pao['preco_medio'], 0.01);
        $this->assertSame('PAO-01', $pao['codigo']);
        $this->assertSame(2, $pao['documentos']);

        $cafe = $porNome['Café expresso'];
        $this->assertEqualsWithDelta(5, $cafe['quantidade'], 0.001);
        $this->assertEqualsWithDelta(3, $cafe['devolvida'], 0.001);
        $this->assertEqualsWithDelta(2850, $cafe['total'], 0.01);

        $t = $r['totais'];
        $this->assertSame(2, $t['artigos']);
        $this->assertSame(3, $t['facturas'], 'a anulada fica de fora');
        $this->assertSame(1, $t['anuladas']);
        $this->assertSame(1, $t['notas']);
        $this->assertEqualsWithDelta(6270, $t['bruto'], 0.01);
        $this->assertEqualsWithDelta(100, $t['descontos'], 0.01);
        $this->assertEqualsWithDelta(2850, $t['devolvido'], 0.01, 'a venda dividida inteira: 1140 + 1710');
        $this->assertEqualsWithDelta(6270 - 100 - 2850, $t['liquido'], 0.01);
        $this->assertEqualsWithDelta(4, $t['quantidade'], 0.001, '(3 − 1) + (5 − 3)');
        $this->assertEqualsWithDelta((6270 - 100) / 3, $t['ticket_medio'], 0.01);

        // Os pesos somam 100 e o maior vem primeiro.
        $this->assertEqualsWithDelta(100, array_sum(array_column($r['produtos'], 'peso')), 0.2);
        $this->assertGreaterThanOrEqual($r['produtos'][1]['liquido'], $r['produtos'][0]['liquido']);

        // Os documentos: 4 facturas (uma anulada) e a nota, a venda dividida com os dois meios.
        $this->assertCount(5, $r['documentos']);
        $doc = collect($r['documentos'])->firstWhere('numero', $dividida->invoice_number);
        $this->assertSame('Dinheiro + ' . \App\Models\Invoicing\PosShiftTransaction::make(['payment_method' => 'tpa'])->payment_method_label, $doc['meio']);
        $this->assertTrue(collect($r['documentos'])->firstWhere('numero', $anulada->invoice_number)['anulada']);
        $this->assertLessThan(0, collect($r['documentos'])->firstWhere('tipo', 'nota')['total']);
    }

    public function test_a_api_devolve_as_vendas_e_o_turno_traz_o_papel_com_produtos(): void
    {
        $turno = $this->turnoAberto();
        $this->venda($turno, [[$this->pao, 2, 1000]], ['cash']);

        $r = $this->getJson(self::RAIZ . "/turnos/{$turno->id}/produtos")->assertOk();
        $this->assertSame('Pão de forma', $r->json('produtos.0.nome'));
        $this->assertEqualsWithDelta(2280, $r->json('totais.liquido'), 0.01);

        $estado = $this->getJson(self::RAIZ . '/turnos/estado')->assertOk();
        $this->assertStringContainsString('detalhe=produtos', $estado->json('turno.exportar.talao_produtos'));
        $this->assertStringContainsString('detalhe=produtos', $estado->json('turno.exportar.pdf_produtos'));
        $this->assertStringNotContainsString('detalhe=', $estado->json('turno.exportar.talao'));

        // Fecha-se como sempre; o fecho com produtos é o mesmo turno com outro papel.
        $fechado = $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 2280])->assertOk();
        $this->assertStringContainsString('detalhe=produtos', $fechado->json('turno.exportar.talao_produtos'));
        $this->getJson(self::RAIZ . "/turnos/{$turno->id}/produtos")->assertOk()->assertJsonPath('totais.facturas', 1);
    }

    public function test_quem_nao_ve_todos_nao_ve_os_produtos_do_turno_de_um_colega(): void
    {
        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $alheio = PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $colega->id, 'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);

        $this->getJson(self::RAIZ . "/turnos/{$alheio->id}/produtos")->assertNotFound();

        $this->comPermissoes('invoicing.pos.reports.all');
        $this->getJson(self::RAIZ . "/turnos/{$alheio->id}/produtos")->assertOk()->assertJsonPath('totais.artigos', 0);
    }

    public function test_o_talao_e_o_pdf_saem_resumidos_ou_com_produtos(): void
    {
        $this->comPermissoes('invoicing.pos.access');
        $turno = $this->turnoAberto();
        $this->venda($turno, [[$this->pao, 2, 1000], [$this->cafe, 1, 500]], ['cash']);

        $resumido = $this->get("/invoicing/pos/export/shift/{$turno->id}/ticket?print=0")->assertOk();
        $resumido->assertSee('RESUMO DE TURNO')->assertDontSee('VENDAS POR PRODUTO')->assertDontSee('Pão de forma');

        $comProdutos = $this->get("/invoicing/pos/export/shift/{$turno->id}/ticket?print=0&detalhe=produtos")->assertOk();
        $comProdutos->assertSee('FECHO DE TURNO COM PRODUTOS')->assertSee('VENDAS POR PRODUTO (2)')
            ->assertSee('Pão de forma')->assertSee('Café expresso')->assertSee('2 × 1,140.00', false)->assertSee('TOTAL LÍQUIDO')
            ->assertSee('DOCUMENTOS (1)');

        $pdf = $this->get("/invoicing/pos/export/shift/{$turno->id}/pdf?detalhe=produtos")->assertOk();
        $this->assertStringContainsString('produtos', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $talaoPdf = $this->get("/invoicing/pos/export/shift/{$turno->id}/ticket?format=pdf&detalhe=produtos")->assertOk();
        $this->assertStringStartsWith('%PDF', $talaoPdf->getContent());
    }

    public function test_o_estado_traz_o_ultimo_fecho_para_reimprimir(): void
    {
        // Sem nenhum turno fechado, não há o que reimprimir.
        $this->assertNull($this->getJson(self::RAIZ . '/turnos/estado')->assertOk()->json('ultimo_fechado'));

        $this->postJson(self::RAIZ . '/turnos/abrir', ['opening_balance' => 0])->assertCreated();
        $turno = PosShift::where('user_id', $this->user->id)->where('status', 'open')->latest('id')->firstOrFail();
        $this->venda($turno, [[$this->pao, 2, 1000]], ['cash']);
        $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 2280])->assertOk();

        $estado = $this->getJson(self::RAIZ . '/turnos/estado')->assertOk();
        $this->assertNull($estado->json('turno'), 'já não há turno aberto');
        $this->assertSame($turno->shift_number, $estado->json('ultimo_fechado.shift_number'));
        $this->assertStringContainsString('detalhe=produtos', $estado->json('ultimo_fechado.exportar.talao_produtos'));
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function artigo(string $nome, string $codigo): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome, 'code' => $codigo, 'type' => 'produto',
            'price' => 1000, 'unit' => 'un', 'category_id' => $categoria->id,
            'tax_type' => 'iva', 'tax_rate_id' => $taxa->id, 'is_active' => true,
        ]);
    }

    private function turnoAberto(): PosShift
    {
        return PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);
    }

    /** @param  list<array{0: Product, 1: float, 2: float}>  $linhas  artigo, quantidade, preço sem IVA (14%) */
    private function factura(array $linhas, float $desconto = 0, ?string $meio = 'cash'): SalesInvoice
    {
        $subtotal = array_sum(array_map(fn ($l) => $l[1] * $l[2], $linhas));
        $imposto = round($subtotal * 0.14, 2);

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id,
            'invoice_number' => 'FR/' . random_int(100000, 999999), 'invoice_date' => now()->toDateString(),
            'status' => 'paid', 'subtotal' => $subtotal, 'tax_amount' => $imposto, 'discount_amount' => $desconto,
            'total' => $subtotal + $imposto - $desconto, 'paid_amount' => $subtotal + $imposto - $desconto,
            'payment_method' => $meio, 'created_by' => $this->user->id,
        ]);

        foreach ($linhas as $i => [$artigo, $q, $preco]) {
            SalesInvoiceItem::create([
                'sales_invoice_id' => $f->id, 'product_id' => $artigo->id, 'product_name' => $artigo->name,
                'description' => $artigo->name, 'quantity' => $q, 'unit_price' => $preco, 'subtotal' => $q * $preco,
                'tax_rate' => 14, 'tax_amount' => round($q * $preco * 0.14, 2), 'total' => round($q * $preco * 1.14, 2),
                'tax_code' => 'NOR', 'tax_country_region' => 'AO', 'order' => $i + 1,
            ]);
        }

        return $f->fresh(['items']);
    }

    /** Uma venda que passou por esta gaveta, um movimento por meio de pagamento. */
    private function venda(PosShift $turno, array $linhas, array $meios, float $desconto = 0): SalesInvoice
    {
        $f = $this->factura($linhas, $desconto, count($meios) > 1 ? 'multiple' : $meios[0]);
        $parte = round((float) $f->total / count($meios), 2);

        foreach ($meios as $meio) {
            $turno->addTransaction([
                'type' => 'invoice', 'reference_type' => SalesInvoice::class, 'reference_id' => $f->id,
                'reference_number' => $f->invoice_number, 'payment_method' => $meio, 'amount' => $parte,
                'description' => 'Venda POS',
            ]);
        }

        return $f;
    }

    private function creditar(SalesInvoice $factura): void
    {
        $emissor = app(EmissorDeNotas::class);

        $emissor->emitirCredito([
            'client_id' => $factura->client_id, 'invoice_id' => $factura->id,
            'issue_date' => now()->toDateString(), 'reason' => 'return', 'type' => 'total',
        ], Collection::make($emissor->linhasDaFactura($factura)));
    }
}
