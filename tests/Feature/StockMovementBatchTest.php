<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\StockManagement;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Documento de Movimentação de Stock (lote MOV/AAAA/NNNNNN).
 *
 * O modal regista várias entradas e saídas de uma vez e imprime o lote como um
 * documento único. Cada asserção aqui corresponde a um defeito concreto: linhas
 * gravadas sem efeito no stock, referências repetidas entre operadores,
 * documentos de uma empresa legíveis por outra.
 */
class StockMovementBatchTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit')
             ->comModulo('invoicing');
    }

    private function modal()
    {
        return Livewire::test(StockManagement::class)->call('openEntryModal');
    }

    /** Grava um lote com as linhas dadas e devolve o componente já testado. */
    private function gravarLote(array $linhas, string $nota = 'Conferência')
    {
        $c = $this->modal()
            ->set('entryWarehouseId', $this->armazem->id)
            ->set('entryNotes', $nota);

        foreach ($linhas as $linha) {
            $c->call('addEntryItem', $linha['produto']->id);
        }

        $itens = $c->get('entryItems');

        foreach ($linhas as $i => $linha) {
            $itens[$i]['op']        = $linha['op'] ?? 'add';
            $itens[$i]['quantity']  = $linha['qtd'];
            $itens[$i]['unit_cost'] = $linha['custo'] ?? 100;
        }

        return $c->set('entryItems', $itens)->call('saveEntry');
    }

    public function test_a_referencia_e_sequencial_por_empresa(): void
    {
        $p = $this->produtoComStock(50);

        $ref1 = $this->gravarLote([['produto' => $p, 'qtd' => 1]])->get('batchReference');
        $ref2 = $this->gravarLote([['produto' => $p, 'qtd' => 1]])->get('batchReference');

        $ano = now()->year;

        $this->assertSame("MOV/{$ano}/000001", $ref1);
        $this->assertSame("MOV/{$ano}/000002", $ref2);
    }

    public function test_cada_empresa_tem_a_sua_sequencia(): void
    {
        $p = $this->produtoComStock(50);
        $this->gravarLote([['produto' => $p, 'qtd' => 1]]);

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(600000000, 699999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $this->assertSame(
            'MOV/' . now()->year . '/000001',
            StockMovement::gerarReferenciaLote($outra->id),
            'a numeração de uma empresa não pode consumir a da outra'
        );
    }

    public function test_todas_as_linhas_ficam_no_mesmo_lote(): void
    {
        $a = $this->produtoComStock(20);
        $b = $this->produtoComStock(20);
        $c = $this->produtoComStock(20);

        $ref = $this->gravarLote([
            ['produto' => $a, 'qtd' => 5, 'op' => 'add'],
            ['produto' => $b, 'qtd' => 3, 'op' => 'add'],
            ['produto' => $c, 'qtd' => 2, 'op' => 'sub'],
        ])->get('batchReference');

        $movimentos = StockMovement::doLote($ref)->get();

        $this->assertCount(3, $movimentos);
        $this->assertCount(1, $movimentos->pluck('warehouse_id')->unique(),
            'o lote é sempre de um só armazém');
        $this->assertSame(2, $movimentos->where('type', 'in')->count());
        $this->assertSame(1, $movimentos->where('type', 'out')->count());
    }

    public function test_o_saldo_resultante_fica_gravado_em_cada_linha(): void
    {
        // Sem isto, uma reimpressão mostrava o stock de hoje — o documento
        // passava a dizer uma coisa que nunca aconteceu.
        $p = $this->produtoComStock(10);

        $ref = $this->gravarLote([['produto' => $p, 'qtd' => 5, 'op' => 'add']])
            ->get('batchReference');

        $this->assertEquals(15, (float) StockMovement::doLote($ref)->first()->balance_after);

        $ref2 = $this->gravarLote([['produto' => $p, 'qtd' => 3, 'op' => 'sub']])
            ->get('batchReference');

        $this->assertEquals(12, (float) StockMovement::doLote($ref2)->first()->balance_after);
    }

    public function test_uma_saida_sem_stock_nao_deixa_movimento_gravado(): void
    {
        // `createExit` insere a linha e só DEPOIS o hook chama removeStock(),
        // que é quem lança. Sem a transacção por linha, o livro ficava a dizer
        // que a mercadoria saiu quando o stock nunca mexeu.
        $bom  = $this->produtoComStock(20);
        $seco = $this->produtoComStock(1);

        $c = $this->gravarLote([
            ['produto' => $bom,  'qtd' => 5,   'op' => 'add'],
            ['produto' => $seco, 'qtd' => 999, 'op' => 'sub'],
        ]);

        $ref = $c->get('batchReference');

        $this->assertSame(1, $c->get('batchOk'), 'só uma linha podia passar');
        $this->assertCount(1, $c->get('batchErrors'));

        $this->assertCount(1, StockMovement::doLote($ref)->get(),
            'a linha falhada não pode ficar no livro');

        $this->assertFalse(
            StockMovement::doLote($ref)->where('product_id', $seco->id)->exists(),
            'o movimento da linha falhada tinha de ser anulado pelo savepoint'
        );

        $this->assertEquals(1, (float) Stock::where('product_id', $seco->id)->sum('quantity'),
            'o stock do produto sem existências fica intacto');
    }

    public function test_quando_nada_e_gravado_nao_ha_documento(): void
    {
        $seco = $this->produtoComStock(0);

        $c = $this->gravarLote([['produto' => $seco, 'qtd' => 5, 'op' => 'sub']]);

        $this->assertNull($c->get('batchReference'), 'sem linhas gravadas não há documento a imprimir');
        $this->assertSame(0, $c->get('batchOk'));
    }

    public function test_o_pdf_do_lote_abre(): void
    {
        $p   = $this->produtoComStock(20);
        $ref = $this->gravarLote([['produto' => $p, 'qtd' => 4]])->get('batchReference');

        $resposta = $this->get(route('invoicing.stock.batch-pdf', ['reference' => $ref]));

        $resposta->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $resposta->getContent());
    }

    public function test_um_lote_curto_cabe_numa_folha_e_vem_numerado(): void
    {
        // O DejaVu Sans embutido é bem mais largo do que a fonte de um browser:
        // um layout que cabia na pré-visualização passava para a segunda folha
        // no PDF. E a numeração só sai certa se o `page_text` correr DEPOIS do
        // render — antes dele só existe uma página e saía "Página 1 de 1" num
        // documento de duas.
        $produtos = collect(range(1, 8))->map(fn () => $this->produtoComStock(30));

        $ref = $this->gravarLote(
            $produtos->map(fn ($p) => ['produto' => $p, 'qtd' => 3])->all()
        )->get('batchReference');

        $binario = $this->get(route('invoicing.stock.batch-pdf', ['reference' => $ref]))->getContent();

        preg_match('#/Type\s*/Pages.*?/Count\s+(\d+)#s', $binario, $m);

        $this->assertSame('1', $m[1] ?? null, 'oito linhas têm de caber numa folha');
    }

    public function test_a_rota_aceita_as_barras_da_referencia(): void
    {
        // A referência leva '/'. Sem o `where` na rota, o Laravel parte o
        // parâmetro e o link do modal dava 404.
        $url = route('invoicing.stock.batch-pdf', ['reference' => 'MOV/2026/000001']);

        $this->assertStringContainsString('/MOV/2026/000001/pdf', $url);
        $this->assertStringNotContainsString('%2F', $url);
    }

    public function test_o_documento_de_uma_empresa_nao_se_le_de_outra(): void
    {
        $p   = $this->produtoComStock(20);
        $ref = $this->gravarLote([['produto' => $p, 'qtd' => 4]])->get('batchReference');

        // Como a sequência é por empresa, o MOV/AAAA/000001 existe em quase
        // todas: sem o filtro por empresa, esta referência abria o documento
        // alheio.
        //
        // Trocar só a sessão não serve para nada — o activeTenantId() só aceita
        // empresas a que o utilizador pertence, e o pedido voltava à empresa
        // original. É preciso um utilizador da OUTRA empresa.
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(600000000, 699999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $modulo = \App\Models\Module::where('slug', 'invoicing')->first();
        $outra->modules()->syncWithoutDetaching([$modulo->id => ['is_active' => true, 'activated_at' => now()]]);

        $intruso = \App\Models\User::create([
            'name' => 'Intruso', 'email' => 'i' . uniqid() . '@x.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $outra->id,
        ]);
        $intruso->tenants()->syncWithoutDetaching([$outra->id]);

        setPermissionsTeamId($outra->id);
        $intruso->givePermissionTo(['invoicing.stock.view', 'invoicing.stock.edit']);

        \App\Models\Subscription::create([
            'tenant_id' => $outra->id,
            'plan_id'   => \App\Models\Plan::where('slug', 'plano-teste')->value('id'),
            'amount'    => 0, 'status' => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        $this->actingAs($intruso);
        session(['active_tenant_id' => $outra->id]);

        $this->get(route('invoicing.stock.batch-pdf', ['reference' => $ref]))
            ->assertNotFound();
    }

    public function test_movimentos_de_outra_origem_ficam_de_fora_do_lote(): void
    {
        $p   = $this->produtoComStock(20);
        $ref = $this->gravarLote([['produto' => $p, 'qtd' => 4]])->get('batchReference');

        // Um movimento de venda não tem lote. Se o NULL fosse tratado como
        // grupo, todas as vendas caíam dentro de qualquer documento.
        StockMovement::semAplicarStock(fn () => StockMovement::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $p->id,
            'type'         => StockMovement::TYPE_OUT,
            'quantity'     => 1,
            'unit_cost'    => 50,
            'user_id'      => $this->user->id,
            'notes'        => 'Venda',
        ]));

        $this->assertCount(1, StockMovement::doLote($ref)->get());
    }

    public function test_o_preview_html_mostra_a_empresa_e_o_armazem(): void
    {
        $p   = $this->produtoComStock(20);
        $ref = $this->gravarLote([['produto' => $p, 'qtd' => 4]])->get('batchReference');

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $ref]))
            ->assertOk()
            ->assertSee($ref)
            ->assertSee($this->armazem->name)
            ->assertSee('não é documento fiscal', false);
    }

    public function test_uma_referencia_inexistente_da_404(): void
    {
        $this->get(route('invoicing.stock.batch-pdf', ['reference' => 'MOV/2026/999999']))
            ->assertNotFound();
    }
}
