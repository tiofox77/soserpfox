<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * A API DO STOCK, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: a lista e os cartões saem da
 * mesma consulta filtrada; a movimentação em lote entra com referência
 * (MOV/AAAA/NNNNNN) e uma saída sem stock falha só nessa linha; ajustar
 * regista um movimento e deixa a linha certa; transferir tira de um armazém
 * e põe noutro; e cada verbo tem a sua permissão.
 */
class ApiDoStockParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/stock';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(int $minimo = 0): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'code' => 'ART-' . uniqid(), 'type' => 'produto', 'price' => 100, 'cost' => 40,
            'unit' => 'un', 'tax_type' => 'isento', 'exemption_reason' => 'M99', 'manage_stock' => true, 'stock_quantity' => 0, 'stock_min' => $minimo, 'is_active' => true,
        ]);
    }

    private function entrada(Product $p, float $qtd): void
    {
        $this->comPermissoes('invoicing.stock.edit');

        $this->postJson(self::RAIZ . '/entrada', [
            'armazem_id' => $this->armazem->id,
            'itens' => [['product_id' => $p->id, 'product_name' => $p->name, 'op' => 'add', 'quantity' => $qtd, 'unit_cost' => 40]],
        ])->assertCreated();
    }

    /** @test */
    public function cada_verbo_tem_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();

        $this->comPermissoes('invoicing.stock.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_editar', false);
        $this->postJson(self::RAIZ . '/entrada', ['armazem_id' => $this->armazem->id, 'itens' => []])->assertForbidden();
        $this->postJson(self::RAIZ . '/transferir', [])->assertForbidden();
    }

    /** A movimentação em lote entra com referência; uma saída sem stock falha só nessa linha. @test */
    public function a_movimentacao_em_lote_entra_com_referencia(): void
    {
        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit');

        $a = $this->artigo();
        $b = $this->artigo();

        $r = $this->postJson(self::RAIZ . '/entrada', [
            'armazem_id' => $this->armazem->id,
            'notas' => 'Contentor 7',
            'itens' => [
                ['product_id' => $a->id, 'product_name' => $a->name, 'op' => 'add', 'quantity' => 10, 'unit_cost' => 40],
                ['product_id' => $b->id, 'product_name' => $b->name, 'op' => 'sub', 'quantity' => 3],
            ],
        ])->assertCreated();

        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d+$#', $r->json('referencia'));
        $this->assertSame(1, $r->json('ok'), 'a entrada entrou');
        $this->assertCount(1, $r->json('erros'), 'a saída sem stock falhou só nessa linha');

        $this->assertEqualsWithDelta(10, Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $a->id)->value('quantity'), 0.001);
        $this->assertEqualsWithDelta(10, $a->fresh()->stock_quantity, 0.001, 'o agregado é mantido pelo observer, uma vez só');
        $this->assertNull(Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $b->id)->first(), 'nada ficou da linha que falhou');
        $this->assertSame($r->json('referencia'), StockMovement::where('product_id', $a->id)->value('batch_reference'));
    }

    /** @test */
    public function a_lista_e_os_cartoes_saem_da_mesma_consulta(): void
    {
        $this->comPermissoes('invoicing.stock.view');

        $baixo = $this->artigo(minimo: 20);
        $cheio = $this->artigo(minimo: 1);
        $this->entrada($baixo, 5);
        $this->entrada($cheio, 50);

        $r = $this->getJson(self::RAIZ)->assertOk();
        $this->assertSame(2, $r->json('resumo.artigos'));
        $this->assertEqualsWithDelta(55, $r->json('resumo.quantidade'), 0.001);
        $this->assertEqualsWithDelta(55 * 40, $r->json('resumo.valor'), 0.01);
        $this->assertSame(1, $r->json('resumo.baixo'));

        $so = $this->getJson(self::RAIZ . '?baixo=1')->assertOk();
        $this->assertSame(1, $so->json('meta.total'));
        $this->assertTrue($so->json('data.0.baixo'));
        $this->assertSame(1, $so->json('resumo.artigos'), 'os cartões seguem o filtro');
    }

    /** @test */
    public function ajustar_regista_um_movimento_e_deixa_a_linha_certa(): void
    {
        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit');

        $a = $this->artigo();
        $this->entrada($a, 10);
        $linha = Stock::where('product_id', $a->id)->first();

        $this->postJson(self::RAIZ . '/ajustar', ['stock_id' => $linha->id, 'nova_quantidade' => 7, 'notas' => 'Contagem'])
            ->assertOk()->assertJsonPath('data.quantidade', 7);

        $this->assertEqualsWithDelta(7, $a->fresh()->stock_quantity, 0.001);
        $this->assertSame(1, StockMovement::where('product_id', $a->id)->where('type', 'adjustment')->count());

        $this->assertCount(2, $this->getJson(self::RAIZ . '/movimentos/' . $a->id)->assertOk()->json('data'));
    }

    /** @test */
    public function transferir_tira_de_um_armazem_e_poe_noutro(): void
    {
        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit', 'invoicing.warehouse-transfer.create');

        $outro = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Norte', 'code' => 'ARM-N-' . uniqid(), 'is_active' => true, 'is_default' => false]);
        $a = $this->artigo();
        $this->entrada($a, 10);
        $linha = Stock::where('product_id', $a->id)->first();

        $this->postJson(self::RAIZ . '/transferir', ['stock_id' => $linha->id, 'para_armazem_id' => $outro->id, 'quantidade' => 4])->assertOk();

        $this->assertEqualsWithDelta(6, $linha->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(4, Stock::where('warehouse_id', $outro->id)->where('product_id', $a->id)->value('quantity'), 0.001);
        $this->assertEqualsWithDelta(10, $a->fresh()->stock_quantity, 0.001, 'o total do artigo não muda');

        $this->postJson(self::RAIZ . '/transferir', ['stock_id' => $linha->id, 'para_armazem_id' => $outro->id, 'quantidade' => 60])
            ->assertStatus(422)->assertJsonValidationErrors('quantidade');
        $this->postJson(self::RAIZ . '/transferir', ['stock_id' => $linha->id, 'para_armazem_id' => $this->armazem->id, 'quantidade' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('para_armazem_id');
    }
}
