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

    /**
     * O QUE O ECRÃ DE SEMPRE PEDIA NA LINHA e a API sempre aceitou: a data de
     * FABRICO e os DIAS DE ALERTA do lote, e os TERMOS do documento.
     *
     * Sem o fabrico não se separam duas remessas do mesmo artigo com a mesma
     * validade; sem os dias de alerta o lote avisa com os 30 dias por omissão,
     * que num fresco chega tarde. Ficaram de fora do editor React até agora —
     * este teste é para não voltarem a cair.
     *
     * @test
     */
    public function o_fabrico_os_dias_de_alerta_e_os_termos_ficam_gravados(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $artigo = $this->artigo(true, true);
        $fabrico = now()->subMonth()->toDateString();

        $r = $this->postJson(self::RAIZ, $this->corpo([
            'terms' => 'Pagamento a 30 dias. Devoluções até 8 dias.',
            'linhas' => [[
                'product_id' => $artigo->id, 'quantity' => 5, 'price' => 200,
                'batch_number' => 'L-FAB-1', 'expiry_date' => now()->addYear()->toDateString(),
                'manufacturing_date' => $fabrico, 'alert_days' => 7,
            ]],
        ]))->assertCreated();

        $factura = PurchaseInvoice::find($r->json('id'));
        $this->assertSame('Pagamento a 30 dias. Devoluções até 8 dias.', $factura->terms);

        $lote = ProductBatch::where('product_id', $artigo->id)->where('batch_number', 'L-FAB-1')->firstOrFail();

        $this->assertSame($fabrico, $lote->manufacturing_date?->toDateString(), 'o fabrico da linha chega ao lote');
        $this->assertSame(7, (int) $lote->alert_days, 'os dias de alerta da linha mandam mais do que os 30 por omissão');
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

    /**
     * A LISTA E A PORTA DIZEM O MESMO — e há UMA porta só para o dinheiro.
     *
     * Os botões que a lista mostra saem do `EmissorDeCompras`, o mesmo que
     * depois aceita ou recusa a acção — um botão que aparece e depois recusa
     * é pior do que um botão que não aparece.
     *
     * E não há aqui nenhum «marcar como paga»: dar uma compra por paga é
     * REGISTAR O PAGAMENTO, que lança o recibo e o movimento de caixa (ver
     * `RegistoDePagamento`). Dois caminhos para o mesmo facto eram duas
     * contabilidades, e uma delas sem rasto na tesouraria.
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

        $this->assertTrue($lista[$registada]['pode_anular']);

        // E depois de anulada, nenhuma das duas.
        $this->postJson(self::RAIZ . '/' . $registada . '/anular')->assertOk();

        $depois = collect(
            $this->getJson('/api/v1/invoicing/react/documentos/facturas-compra')->assertOk()->json('data')
        )->keyBy('id');

        $this->assertFalse($depois[$registada]['pode_anular']);

        // E a lista oferece duplicar — que é o outro caminho que aqui existe.
        $this->assertTrue(
            $this->getJson('/api/v1/invoicing/react/documentos/facturas-compra/opcoes')->assertOk()->json('pode_duplicar')
        );
    }

    /**
     * O FORNECEDOR RÁPIDO: AS OPÇÕES DIZEM SE ELE SE PODE CRIAR AQUI.
     *
     * A factura do fornecedor está na mão e ele ainda não está na ficha.
     * Criá-lo daqui é permissão própria (`invoicing.suppliers.create`), outra
     * coisa que não registar compras: sem ela o botão não aparece — e a
     * `/catalogos/fornecedores` recusa na mesma.
     *
     * @test
     */
    public function as_opcoes_dizem_se_o_fornecedor_se_pode_criar_aqui(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $this->getJson(self::RAIZ . '/opcoes')
            ->assertOk()
            ->assertJsonPath('criar_parte.tipo', 'fornecedor')
            ->assertJsonPath('criar_parte.pode', false)
            ->assertJsonPath('criar_parte.pais_padrao', 'AO');

        $this->comPermissoes('invoicing.suppliers.create');

        $this->getJson(self::RAIZ . '/opcoes')
            ->assertOk()
            ->assertJsonPath('criar_parte.pode', true);
    }

    /**
     * O FORNECEDOR RÁPIDO ENTRA PELA PORTA DE SEMPRE, e fica escolhível.
     *
     * Não há um segundo caminho de criação: é a `/catalogos/fornecedores`, com
     * as regras do esquema (nome com pelo menos três letras, país em código
     * ISO de duas). E, criado, tem de aparecer nas opções da compra — é lá que
     * o ecrã o vai buscar para o pôr escolhido no documento.
     *
     * @test
     */
    public function o_fornecedor_rapido_grava_pela_porta_dos_catalogos(): void
    {
        $this->comPermissoes(
            'invoicing.purchases.invoices.create',
            'invoicing.suppliers.view',
            'invoicing.suppliers.create'
        );

        $porta = '/api/v1/invoicing/react/catalogos/fornecedores';

        $rapido = fn (array $por = []) => array_merge([
            'type' => 'pessoa_juridica',
            'name' => 'Fornecedor do Balcão, Lda',
            'nif' => '5000000321',
            'email' => null,
            'phone' => null,
            'address' => null,
            'country' => 'AO',
        ], $por);

        // As mesmas regras do ecrã completo: nome curto e país por extenso
        // não passam por a criação ser «rápida».
        $this->postJson($porta, $rapido(['name' => 'ab']))->assertJsonValidationErrors('name');
        $this->postJson($porta, $rapido(['country' => 'Angola']))->assertJsonValidationErrors('country');

        $id = $this->postJson($porta, $rapido())->assertCreated()->json('data.id');

        $fornecedores = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('fornecedores'));

        $this->assertTrue(
            $fornecedores->contains('id', $id),
            'o fornecedor criado do emissor tem de aparecer nas opções da compra'
        );
    }
}
