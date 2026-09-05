<?php

namespace Tests\Feature\Compras;

use App\Models\Compras\Encomenda;
use App\Models\Compras\Requisicao;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Compras\FluxoDaEncomenda;
use App\Services\Compras\FluxoDaRequisicao;
use Tests\TenantTestCase;

/**
 * O circuito das compras: requisição → encomenda → recepção → factura.
 *
 * O que estes ensaios prendem é sobretudo UMA coisa: o stock entra uma vez e
 * uma só. Tudo o resto (estados, parcelas, autoridade) existe para servir isso.
 */
class CircuitoDeComprasTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('compras');
    }

    private function fornecedor(): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor de Teste',
            'type' => 'pessoa_juridica',
            'is_active' => true,
        ]);
    }

    private function encomendaCom(Product $produto, float $qtd = 10, float $preco = 1000): Encomenda
    {
        return app(FluxoDaEncomenda::class)->criar($this->tenant->id, $this->user->id, [
            'supplier_id' => $this->fornecedor()->id,
            'warehouse_id' => $this->armazem->id,
        ], [
            ['product_id' => $produto->id, 'descricao' => $produto->name, 'quantidade' => $qtd, 'preco_unitario' => $preco],
        ]);
    }

    private function stockDe(Product $produto): float
    {
        return (float) Stock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->armazem->id)
            ->where('product_id', $produto->id)
            ->value('quantity');
    }

    /** @test */
    public function encomendar_nao_mexe_no_stock(): void
    {
        $produto = $this->produtoComStock(10);
        $encomenda = $this->encomendaCom($produto, 50);

        app(FluxoDaEncomenda::class)->enviar($encomenda, $this->tenant->id);

        // Pedir 50 unidades não põe 50 unidades no armazém.
        $this->assertSame(10.0, $this->stockDe($produto));
    }

    /** @test */
    public function receber_da_entrada_de_stock(): void
    {
        $produto = $this->produtoComStock(10);
        $encomenda = $this->encomendaCom($produto, 50);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);

        $item = $encomenda->itens()->first();
        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$item->id => 50]);

        $this->assertSame(60.0, $this->stockDe($produto));
        $this->assertSame('recebida', $encomenda->fresh()->estado);

        // O movimento fica no livro, apontado à encomenda.
        $this->assertDatabaseHas('invoicing_stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
            'type' => 'in',
            'reference_type' => Encomenda::class,
            'reference_id' => $encomenda->id,
        ]);
    }

    /**
     * Recepção parcial: entra o que chegou, e a encomenda fica à espera do resto.
     *
     * @test
     */
    public function receber_em_parte_deixa_o_resto_a_espera(): void
    {
        $produto = $this->produtoComStock(0);
        $encomenda = $this->encomendaCom($produto, 30);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);

        $item = $encomenda->itens()->first();
        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$item->id => 12]);

        $this->assertSame(12.0, $this->stockDe($produto));
        $this->assertSame('parcial', $encomenda->fresh()->estado);
        $this->assertSame(18.0, $encomenda->fresh()->itens->first()->porReceber());

        // O resto chega mais tarde.
        $fluxo->receber($encomenda->fresh(), $this->tenant->id, $this->user->id, [$item->id => 18]);

        $this->assertSame(30.0, $this->stockDe($produto));
        $this->assertSame('recebida', $encomenda->fresh()->estado);
    }

    /** Não se recebe mais do que se encomendou — e diz-se quanto falta. */
    public function test_receber_a_mais_e_recusado(): void
    {
        $produto = $this->produtoComStock(0);
        $encomenda = $this->encomendaCom($produto, 10);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);

        $item = $encomenda->itens()->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/mais/');

        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$item->id => 11]);
    }

    /**
     * O ENSAIO QUE MAIS IMPORTA.
     *
     * A mercadoria entrou na recepção. A factura gerada a seguir NÃO pode
     * voltar a dar entrada — nem ao nascer, nem quando sair de rascunho. Sem a
     * marca `stock_ja_entrou`, o observer das compras punha no sistema o dobro
     * do que chegou ao armazém.
     *
     * @test
     */
    public function facturar_o_que_ja_foi_recebido_nao_duplica_o_stock(): void
    {
        $produto = $this->produtoComStock(0);
        $encomenda = $this->encomendaCom($produto, 40, 500);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);

        $item = $encomenda->itens()->first();
        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$item->id => 40]);
        $this->assertSame(40.0, $this->stockDe($produto));

        $factura = $fluxo->facturar($encomenda->fresh(), $this->tenant->id, $this->user->id);

        $this->assertTrue($factura->stock_ja_entrou);
        $this->assertSame(40.0, $this->stockDe($produto), 'A factura não pode repetir a entrada.');

        // E também não repete quando a factura passa a assumida.
        $factura->update(['status' => 'pending']);
        $this->assertSame(40.0, $this->stockDe($produto), 'Sair de rascunho não pode repetir a entrada.');

        // Só há UM movimento de entrada desta encomenda.
        $this->assertSame(1, StockMovement::where('tenant_id', $this->tenant->id)
            ->where('product_id', $produto->id)
            ->where('reference_type', Encomenda::class)
            ->count());
    }

    /**
     * A factura de compra normal (sem recepção) continua a dar entrada como
     * sempre deu — a marca nova não pode ter mudado o caminho de sempre.
     *
     * @test
     */
    public function a_factura_de_compra_de_sempre_continua_a_dar_entrada(): void
    {
        $produto = $this->produtoComStock(5);

        // O caminho verdadeiro do ecrã de compras: nasce rascunho, recebe as
        // linhas, e só no fim muda para um estado que dá entrada de stock.
        $factura = PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->fornecedor()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $factura->items()->create([
            'product_id' => $produto->id,
            'product_name' => $produto->name,
            'quantity' => 7,
            'unit' => 'UN',
            'unit_price' => 100,
        ]);

        $factura->update(['status' => 'pending']);

        $this->assertSame(12.0, $this->stockDe($produto));
        $this->assertFalse((bool) $factura->fresh()->stock_ja_entrou);
    }

    /** Facturar só o que chegou — não a encomenda toda. */
    public function test_a_factura_leva_so_o_recebido(): void
    {
        $produto = $this->produtoComStock(0);
        $encomenda = $this->encomendaCom($produto, 100, 250);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);

        $item = $encomenda->itens()->first();
        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$item->id => 30]);

        $factura = $fluxo->facturar($encomenda->fresh(), $this->tenant->id, $this->user->id);

        $this->assertSame(1, $factura->items()->count());
        $this->assertEquals(30, (float) $factura->items()->first()->quantity);
    }

    /** Uma encomenda com mercadoria recebida não se cancela num clique. */
    public function test_nao_se_cancela_encomenda_ja_recebida(): void
    {
        $produto = $this->produtoComStock(0);
        $encomenda = $this->encomendaCom($produto, 10);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);
        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$encomenda->itens()->first()->id => 5]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/devolução/');

        $fluxo->cancelar($encomenda->fresh(), $this->tenant->id);
    }

    // ── Requisições ──────────────────────────────────────────────────────

    /** @test */
    public function requisicao_aprovada_vira_encomenda_so_com_o_que_falta(): void
    {
        $produto = $this->produtoComStock(0);
        $reqFluxo = app(FluxoDaRequisicao::class);

        $req = $reqFluxo->criar($this->tenant->id, $this->user->id, [], [
            ['product_id' => $produto->id, 'descricao' => $produto->name, 'quantidade' => 20, 'custo_estimado' => 300],
        ]);

        $reqFluxo->submeter($req, $this->tenant->id);
        $reqFluxo->aprovar($req, $this->tenant->id, $this->user->id);

        $enc = app(FluxoDaEncomenda::class)
            ->daRequisicao($req->fresh(), $this->tenant->id, $this->user->id, $this->fornecedor()->id);

        $this->assertSame(20.0, (float) $enc->itens->first()->quantidade);
        $this->assertSame(300.0, (float) $enc->itens->first()->preco_unitario);
        $this->assertSame('encomendada', $req->fresh()->estado);
        $this->assertSame(0.0, $req->fresh()->itens->first()->porEncomendar());

        // Segunda tentativa não tem nada para levar.
        $this->expectException(\InvalidArgumentException::class);
        app(FluxoDaEncomenda::class)->daRequisicao($req->fresh(), $this->tenant->id, $this->user->id, $this->fornecedor()->id);
    }

    /** Recusar exige motivo — a mesma regra de perder um negócio no CRM. */
    public function test_recusar_sem_motivo_e_recusado(): void
    {
        $produto = $this->produtoComStock(0);
        $fluxo = app(FluxoDaRequisicao::class);

        $req = $fluxo->criar($this->tenant->id, $this->user->id, [], [
            ['product_id' => $produto->id, 'descricao' => 'X', 'quantidade' => 1],
        ]);
        $fluxo->submeter($req, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $fluxo->rejeitar($req, $this->tenant->id, $this->user->id, '   ');
    }

    /** Um rascunho sem linhas não é uma requisição. */
    public function test_requisicao_sem_linhas_e_recusada(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FluxoDaRequisicao::class)->criar($this->tenant->id, $this->user->id, [], [
            ['descricao' => '', 'quantidade' => 0],
        ]);
    }

    /** Uma encomenda de outra empresa não se toca. */
    public function test_encomenda_de_outra_empresa_e_recusada(): void
    {
        $produto = $this->produtoComStock(0);
        $encomenda = $this->encomendaCom($produto, 5);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/outra empresa/');

        app(FluxoDaEncomenda::class)->enviar($encomenda, $this->tenant->id + 99999);
    }

    /** O custo do artigo passa a ser o preço a que se comprou. */
    public function test_receber_actualiza_o_custo_do_artigo(): void
    {
        $produto = $this->produtoComStock(0, 5000);
        $this->assertSame(2500.0, (float) $produto->fresh()->cost);

        $encomenda = $this->encomendaCom($produto, 10, 3200);
        $fluxo = app(FluxoDaEncomenda::class);
        $fluxo->enviar($encomenda, $this->tenant->id);
        $fluxo->receber($encomenda, $this->tenant->id, $this->user->id, [$encomenda->itens()->first()->id => 10]);

        $this->assertSame(3200.0, (float) $produto->fresh()->cost);
    }
}
