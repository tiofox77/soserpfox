<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Supplier;
use Tests\TenantTestCase;

/**
 * A API DA FACTURA DE COMPRA, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: a taxa vem do `TaxResolver`
 * mesmo que o pedido minta; o armazém é sempre obrigatório; registar a
 * compra dá ENTRADA do stock ao preço da compra, que passa a ser o custo do
 * artigo; o rascunho não mexe em nada; o lote nasce da compra; e a factura
 * sai com número, hash e os campos SAFT a fechar.
 */
class ApiDaCompraParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/compra';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function fornecedor(): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor ' . uniqid(),
            'nif' => '5000000000',
            'type' => 'pessoa_juridica',
            'is_active' => true,
        ]);
    }

    private function artigo(bool $comIva = true, bool $lotes = false): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = $comIva ? Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']) : null;

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1500, 'cost' => 0,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => $comIva ? 'iva' : 'isento',
            'tax_rate_id' => $taxa?->id, 'exemption_reason' => $comIva ? null : 'M99',
            'manage_stock' => true, 'stock_quantity' => 0, 'track_batches' => $lotes, 'is_active' => true,
        ]);
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'supplier_id' => $this->fornecedor()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 3, 'price' => 800]],
        ], $por);
    }

    /** @test */
    public function sem_permissao_de_criar_nao_ha_nada(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->postJson(self::RAIZ . '/calcular', ['linhas' => []])->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /** As opções trazem o CUSTO do artigo, não o preço de venda. @test */
    public function as_opcoes_propoem_o_custo_do_artigo(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $artigo = $this->artigo();
        $artigo->update(['cost' => 640]);

        $r = $this->getJson(self::RAIZ . '/opcoes')->assertOk();

        $doEcra = collect($r->json('artigos'))->firstWhere('id', $artigo->id);

        $this->assertEqualsWithDelta(640, $doEcra['cost'], 0.01);
        $this->assertArrayNotHasKey('price', $doEcra, 'o preço de venda não entra numa compra');
    }

    /** A taxa é a do artigo, não a do pedido. @test */
    public function a_taxa_vem_do_artigo_mesmo_que_o_pedido_minta(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $r = $this->postJson(self::RAIZ . '/calcular', [
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000, 'tax_rate' => 0]],
        ])->assertOk();

        $this->assertEqualsWithDelta(14, $r->json('linhas.0.tax_rate'), 0.01);
    }

    /**
     * REGISTAR A COMPRA DÁ ENTRADA DO STOCK, ao preço da compra, que passa a
     * ser o custo do artigo. E a factura sai com número, hash e SAFT a fechar.
     *
     * @test
     */
    public function registar_a_compra_da_entrada_do_stock_ao_custo_da_compra(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $artigo = $this->artigo();

        $r = $this->postJson(self::RAIZ, $this->corpo([
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 3, 'price' => 800]],
        ]))->assertCreated();

        $f = PurchaseInvoice::find($r->json('id'));

        $this->assertNotEmpty($f->invoice_number, 'numera-se');
        $this->assertNotEmpty($f->saft_hash, 'entra na cadeia');
        $this->assertSame('pending', $f->status);
        $this->assertSame(1, $f->items()->count());
        $this->assertEqualsWithDelta(2400, $f->net_total, 0.01);
        $this->assertEqualsWithDelta(336, $f->tax_payable, 0.01);
        $this->assertEqualsWithDelta($f->net_total + $f->tax_payable, $f->gross_total, 0.01, 'net + tax = gross');

        $artigo = $artigo->fresh();

        $this->assertEqualsWithDelta(3, $artigo->stock_quantity, 0.001, 'a mercadoria entrou');
        $this->assertEqualsWithDelta(800, $artigo->cost, 0.01, 'o preço da compra passou a ser o custo');
    }

    /** O rascunho não mexe no stock nem no custo. @test */
    public function um_rascunho_nao_da_entrada_de_nada(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $artigo = $this->artigo();

        $r = $this->postJson(self::RAIZ, $this->corpo([
            'status' => 'draft',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 3, 'price' => 800]],
        ]))->assertCreated();

        $this->assertSame('draft', PurchaseInvoice::find($r->json('id'))->status);

        $artigo = $artigo->fresh();

        $this->assertEqualsWithDelta(0, $artigo->stock_quantity, 0.001);
        $this->assertEqualsWithDelta(0, $artigo->cost, 0.01);
    }

    /** O lote e a validade nascem da compra. @test */
    public function o_lote_nasce_da_compra(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $artigo = $this->artigo(true, true);

        $this->postJson(self::RAIZ, $this->corpo([
            'linhas' => [[
                'product_id' => $artigo->id, 'quantity' => 5, 'price' => 200,
                'batch_number' => 'L-2026-01', 'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ]))->assertCreated();

        $lote = ProductBatch::where('product_id', $artigo->id)->where('batch_number', 'L-2026-01')->first();

        $this->assertNotNull($lote, 'o lote foi criado pela compra');
        $this->assertEqualsWithDelta(5, $lote->quantity, 0.001);
    }

    /** O armazém é sempre obrigatório: a compra dá entrada de stock. @test */
    public function sem_armazem_ou_fornecedor_o_servidor_recusa_e_diz_onde(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $this->postJson(self::RAIZ, $this->corpo(['warehouse_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');

        $this->postJson(self::RAIZ, $this->corpo(['supplier_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('supplier_id');

        $this->assertSame(0, PurchaseInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    /** Sem linhas não há factura. @test */
    public function sem_linhas_nao_ha_factura(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $this->postJson(self::RAIZ, $this->corpo(['linhas' => []]))->assertJsonValidationErrors('linhas');
    }

    /* ─── MARCAR COMO PAGA: o acerto de quem já pagou por fora ─────────── */

    /**
     * DÁ A DÍVIDA POR SALDADA, e não lança dinheiro nenhum em tesouraria.
     *
     * É o botão que o ecrã em Blade tinha ao lado do de pagar, e a diferença
     * entre os dois é essa: pagar emite recibo e mexe na caixa; marcar como
     * paga só fecha a conta de uma factura que já foi liquidada noutro sítio.
     *
     * @test
     */
    public function marcar_como_paga_fecha_a_conta_da_factura(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create', 'invoicing.purchases.invoices.edit');

        $id = $this->postJson(self::RAIZ, $this->corpo(['status' => 'pending']))->assertCreated()->json('id');

        $this->postJson(self::RAIZ . '/' . $id . '/pagar')
            ->assertOk()
            ->assertJsonPath('estado', 'paid');

        $f = PurchaseInvoice::findOrFail($id);

        $this->assertSame('paid', $f->status);
        $this->assertEqualsWithDelta((float) $f->total, (float) $f->paid_amount, 0.01);
    }

    /** Já paga, ou anulada, não se marca outra vez — e o servidor diz porquê. @test */
    public function uma_factura_paga_ou_anulada_nao_se_marca_como_paga(): void
    {
        $this->comPermissoes(
            'invoicing.purchases.invoices.create',
            'invoicing.purchases.invoices.edit',
            'invoicing.purchases.invoices.delete',
        );

        $paga = $this->postJson(self::RAIZ, $this->corpo(['status' => 'paid']))->assertCreated()->json('id');
        $this->postJson(self::RAIZ . '/' . $paga . '/pagar')->assertStatus(422);

        $anulada = $this->postJson(self::RAIZ, $this->corpo(['status' => 'pending']))->assertCreated()->json('id');
        $this->postJson(self::RAIZ . '/' . $anulada . '/anular')->assertOk();
        $this->postJson(self::RAIZ . '/' . $anulada . '/pagar')->assertStatus(422);

        $this->assertSame('cancelled', PurchaseInvoice::findOrFail($anulada)->status);
    }

    /** Marcar como paga é alterar o documento: exige a permissão de EDITAR. @test */
    public function sem_permissao_de_editar_nao_se_marca_como_paga(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $id = $this->postJson(self::RAIZ, $this->corpo(['status' => 'pending']))->assertCreated()->json('id');

        $this->postJson(self::RAIZ . '/' . $id . '/pagar')->assertForbidden();

        $this->assertSame('pending', PurchaseInvoice::findOrFail($id)->status);
    }

    /**
     * A LISTA E A PORTA DIZEM O MESMO.
     *
     * Os botões que a lista mostra saem do `EmissorDeCompras`, o mesmo que
     * depois aceita ou recusa a acção — um botão que aparece e depois recusa é
     * pior do que um botão que não aparece.
     *
     * @test
     */
    public function a_lista_diz_que_accoes_cada_compra_aceita(): void
    {
        $this->comPermissoes(
            'invoicing.purchases.invoices.view',
            'invoicing.purchases.invoices.create',
            'invoicing.purchases.invoices.edit',
            'invoicing.purchases.invoices.delete',
        );

        $rascunho = $this->postJson(self::RAIZ, $this->corpo(['status' => 'draft']))->assertCreated()->json('id');
        $registada = $this->postJson(self::RAIZ, $this->corpo(['status' => 'pending']))->assertCreated()->json('id');

        $lista = collect(
            $this->getJson('/api/v1/invoicing/react/documentos/facturas-compra')->assertOk()->json('data')
        )->keyBy('id');

        $this->assertFalse($lista[$rascunho]['pode_anular'], 'um rascunho não se anula');
        $this->assertTrue($lista[$rascunho]['pode_marcar_paga']);

        $this->assertTrue($lista[$registada]['pode_anular']);
        $this->assertTrue($lista[$registada]['pode_marcar_paga']);

        // E depois de anulada, nenhuma das duas.
        $this->postJson(self::RAIZ . '/' . $registada . '/anular')->assertOk();

        $depois = collect(
            $this->getJson('/api/v1/invoicing/react/documentos/facturas-compra')->assertOk()->json('data')
        )->keyBy('id');

        $this->assertFalse($depois[$registada]['pode_anular']);
        $this->assertFalse($depois[$registada]['pode_marcar_paga']);

        // E a lista oferece duplicar — que é o outro caminho que aqui existe.
        $this->assertTrue(
            $this->getJson('/api/v1/invoicing/react/documentos/facturas-compra/opcoes')->assertOk()->json('pode_duplicar')
        );
    }
}
