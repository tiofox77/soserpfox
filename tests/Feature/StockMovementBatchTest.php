<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * Documento de Movimentação de Stock (lote MOV/AAAA/NNNNNN).
 *
 * O ecrã regista várias entradas e saídas de uma vez e imprime o lote como um
 * documento único. Cada asserção aqui corresponde a um defeito concreto: linhas
 * gravadas sem efeito no stock, referências repetidas entre operadores,
 * documentos de uma empresa legíveis por outra.
 *
 * O modal saiu do Livewire para o React e o lote entra agora por
 * `POST /api/v1/invoicing/react/stock/entrada` — as regras não mudaram de sítio
 * nenhum: continuam no `MovimentacaoDeStock` e nos ganchos do `StockMovement`.
 * O documento impresso (PDF e pré-visualização) continua a ser servido pelo
 * `StockMovementController`, nas mesmas moradas de sempre.
 */
class StockMovementBatchTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/stock';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit')
             ->comModulo('invoicing');
    }

    /**
     * Grava um lote com as linhas dadas e devolve a resposta da API.
     *
     * @param  array<int, array{produto: Product, qtd: float, op?: string, custo?: float}>  $linhas
     */
    private function gravarLote(array $linhas, string $nota = 'Conferência'): TestResponse
    {
        return $this->postJson(self::RAIZ . '/entrada', [
            'armazem_id' => $this->armazem->id,
            'notas' => $nota,
            'itens' => array_map(fn (array $l) => [
                'product_id' => $l['produto']->id,
                'product_name' => $l['produto']->name,
                'op' => $l['op'] ?? 'add',
                'quantity' => $l['qtd'],
                'unit_cost' => $l['custo'] ?? 100,
            ], $linhas),
        ]);
    }

    /** A referência do lote acabado de gravar. */
    private function referencia(array $linhas, string $nota = 'Conferência'): string
    {
        return $this->gravarLote($linhas, $nota)->assertCreated()->json('referencia');
    }

    public function test_a_referencia_e_sequencial_por_empresa(): void
    {
        $p = $this->produtoComStock(50);

        $ref1 = $this->referencia([['produto' => $p, 'qtd' => 1]]);
        $ref2 = $this->referencia([['produto' => $p, 'qtd' => 1]]);

        $ano = now()->year;

        $this->assertSame("MOV/{$ano}/000001", $ref1);
        $this->assertSame("MOV/{$ano}/000002", $ref2);
    }

    public function test_cada_empresa_tem_a_sua_sequencia(): void
    {
        $p = $this->produtoComStock(50);
        $this->gravarLote([['produto' => $p, 'qtd' => 1]])->assertCreated();

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $this->assertSame(
            'MOV/' . now()->year . '/000001',
            StockMovement::comLoteReservado($outra->id, fn (string $r) => $r),
            'a numeração de uma empresa não pode consumir a da outra'
        );
    }

    public function test_todas_as_linhas_ficam_no_mesmo_lote(): void
    {
        $a = $this->produtoComStock(20);
        $b = $this->produtoComStock(20);
        $c = $this->produtoComStock(20);

        $ref = $this->referencia([
            ['produto' => $a, 'qtd' => 5, 'op' => 'add'],
            ['produto' => $b, 'qtd' => 3, 'op' => 'add'],
            ['produto' => $c, 'qtd' => 2, 'op' => 'sub'],
        ]);

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

        $ref = $this->referencia([['produto' => $p, 'qtd' => 5, 'op' => 'add']]);

        $this->assertEquals(15, (float) StockMovement::doLote($ref)->first()->balance_after);

        $ref2 = $this->referencia([['produto' => $p, 'qtd' => 3, 'op' => 'sub']]);

        $this->assertEquals(12, (float) StockMovement::doLote($ref2)->first()->balance_after);
    }

    public function test_uma_saida_sem_stock_nao_deixa_movimento_gravado(): void
    {
        // `createExit` insere a linha e só DEPOIS o hook chama removeStock(),
        // que é quem lança. Sem a transacção por linha, o livro ficava a dizer
        // que a mercadoria saiu quando o stock nunca mexeu.
        $bom  = $this->produtoComStock(20);
        $seco = $this->produtoComStock(1);

        $r = $this->gravarLote([
            ['produto' => $bom,  'qtd' => 5,   'op' => 'add'],
            ['produto' => $seco, 'qtd' => 999, 'op' => 'sub'],
        ])->assertCreated();

        $ref = $r->json('referencia');

        $this->assertSame(1, $r->json('ok'), 'só uma linha podia passar');
        $this->assertCount(1, $r->json('erros'));

        $this->assertCount(1, StockMovement::doLote($ref)->get(),
            'a linha falhada não pode ficar no livro');

        $this->assertFalse(
            StockMovement::doLote($ref)->where('product_id', $seco->id)->exists(),
            'o movimento da linha falhada tinha de ser anulado pelo savepoint'
        );

        $this->assertEquals(1, (float) Stock::where('product_id', $seco->id)->sum('quantity'),
            'o stock do produto sem existências fica intacto');
    }

    public function test_uma_linha_falhada_nao_apaga_a_auditoria_das_irmas(): void
    {
        // O rollback de um savepoint dispara o MESMO evento que o de uma
        // transacção de topo. O ouvinte da auditoria limpava o buffer inteiro,
        // levando com ele as linhas dos movimentos irmãos que já tinham passado
        // e que iam mesmo ser confirmados: ficavam no livro sem rasto nenhum na
        // trilha. E a trilha é append-only — não há como repor depois.
        $bom1 = $this->produtoComStock(20);
        $bom2 = $this->produtoComStock(20);
        $seco = $this->produtoComStock(1);

        \App\Models\AuditTrail::where('tenant_id', $this->tenant->id)->delete();

        $r = $this->gravarLote([
            ['produto' => $bom1, 'qtd' => 5,   'op' => 'add'],
            ['produto' => $bom2, 'qtd' => 5,   'op' => 'add'],
            ['produto' => $seco, 'qtd' => 999, 'op' => 'sub'],
        ])->assertCreated();

        $this->assertSame(2, $r->json('ok'));

        app(\App\Services\Audit\AuditRecorder::class)->despejar();

        $auditados = \App\Models\AuditTrail::where('tenant_id', $this->tenant->id)
            ->where('auditable_type', StockMovement::class)
            ->where('event', 'created')
            ->pluck('auditable_id');

        $gravados = StockMovement::doLote($r->json('referencia'))->pluck('id');

        $this->assertCount(2, $gravados);

        foreach ($gravados as $id) {
            $this->assertTrue($auditados->contains($id),
                "o movimento {$id} foi gravado mas não deixou rasto na trilha");
        }
    }

    public function test_quando_nada_e_gravado_nao_ha_documento(): void
    {
        $seco = $this->produtoComStock(0);

        $r = $this->gravarLote([['produto' => $seco, 'qtd' => 5, 'op' => 'sub']]);

        // Nada passou: a API recusa o lote inteiro e não devolve documento
        // nenhum para imprimir.
        $r->assertStatus(422)->assertJsonStructure(['errors' => ['itens']]);

        $this->assertNull($r->json('referencia'), 'sem linhas gravadas não há documento a imprimir');
        $this->assertSame(0, StockMovement::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_o_pdf_do_lote_abre(): void
    {
        $p   = $this->produtoComStock(20);
        $ref = $this->referencia([['produto' => $p, 'qtd' => 4]]);

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

        $ref = $this->referencia(
            $produtos->map(fn ($p) => ['produto' => $p, 'qtd' => 3])->all()
        );

        $binario = $this->get(route('invoicing.stock.batch-pdf', ['reference' => $ref]))->getContent();

        preg_match('#/Type\s*/Pages.*?/Count\s+(\d+)#s', $binario, $m);

        $this->assertSame('1', $m[1] ?? null, 'oito linhas têm de caber numa folha');
    }

    public function test_a_rota_aceita_as_barras_da_referencia(): void
    {
        // A referência leva '/'. Sem o `where` na rota, o Laravel parte o
        // parâmetro e o link do ecrã dava 404.
        $url = route('invoicing.stock.batch-pdf', ['reference' => 'MOV/2026/000001']);

        $this->assertStringContainsString('/MOV/2026/000001/pdf', $url);
        $this->assertStringNotContainsString('%2F', $url);
    }

    /** E o ecrã do lote aponta mesmo para esse endereço — é o botão do PDF. */
    public function test_a_api_devolve_o_endereco_do_documento(): void
    {
        $p = $this->produtoComStock(20);

        $r = $this->gravarLote([['produto' => $p, 'qtd' => 4]])->assertCreated();

        $this->assertSame(
            '/invoicing/stock/movimentacao/' . $r->json('referencia') . '/pdf',
            $r->json('pdf')
        );
    }

    public function test_o_documento_de_uma_empresa_nao_se_le_de_outra(): void
    {
        $p   = $this->produtoComStock(20);
        $ref = $this->referencia([['produto' => $p, 'qtd' => 4]]);

        // Como a sequência é por empresa, o MOV/AAAA/000001 existe em quase
        // todas: sem o filtro por empresa, esta referência abria o documento
        // alheio.
        //
        // Trocar só a sessão não serve para nada — o activeTenantId() só aceita
        // empresas a que o utilizador pertence, e o pedido voltava à empresa
        // original. É preciso um utilizador da OUTRA empresa.
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
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
        $ref = $this->referencia([['produto' => $p, 'qtd' => 4]]);

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
        $ref = $this->referencia([['produto' => $p, 'qtd' => 4]]);

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $ref]))
            ->assertOk()
            ->assertSee($ref)
            ->assertSee($this->armazem->name)
            ->assertSee('não é documento fiscal', false);
    }

    public function test_a_reimpressao_sobrevive_ao_artigo_sair_do_catalogo(): void
    {
        // O Product tem soft delete. Sem `withTrashed`, a reimpressão de um lote
        // antigo trocava as linhas por "(produto removido)" e perdia o código —
        // e é na reimpressão que o documento serve para alguma coisa.
        $p   = $this->produtoComStock(20);
        $ref = $this->referencia([['produto' => $p, 'qtd' => 4]]);

        $p->delete();

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $ref]))
            ->assertOk()
            ->assertSee($p->name)
            ->assertSee($p->code)
            ->assertDontSee('(produto removido)');
    }

    public function test_uma_referencia_inexistente_da_404(): void
    {
        $this->get(route('invoicing.stock.batch-pdf', ['reference' => 'MOV/2026/999999']))
            ->assertNotFound();
    }

    public function test_uma_quantidade_abaixo_da_precisao_da_coluna_e_recusada(): void
    {
        // A coluna quantity é decimal(10,2). Quantidades de três casas eram
        // aceites, gravavam 0,00 e o stock não mexia — o operador via a linha
        // registada e nada acontecia.
        $p = $this->produtoComStock(10);

        $r = $this->gravarLote([['produto' => $p, 'qtd' => 0.004]]);

        $r->assertStatus(422)->assertJsonValidationErrors('itens.0.quantity');

        $this->assertNull($r->json('referencia'));
        $this->assertEquals(10, (float) Stock::where('product_id', $p->id)->sum('quantity'));
    }

    public function test_uma_transferencia_abaixo_da_precisao_e_recusada(): void
    {
        // O mesmo defeito no ecrã vizinho, e pior: além de não transferir nada,
        // criava no destino uma linha de stock a zero. Essa linha fantasma põe o
        // produto em regime multi-armazém e faz o POS deixar de usar o agregado
        // legado — o artigo passa a aparecer esgotado na caixa.
        $this->comPermissoes('invoicing.warehouse-transfer.create');

        $p = $this->produtoComStock(10);

        $destino = \App\Models\Invoicing\Warehouse::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Armazém Secundário',
            'code' => 'SEC' . random_int(100, 999), 'is_default' => false, 'is_active' => true,
        ]);

        $linha = Stock::where('product_id', $p->id)->where('warehouse_id', $this->armazem->id)->first();

        $this->postJson(self::RAIZ . '/transferir', [
            'stock_id' => $linha->id,
            'para_armazem_id' => $destino->id,
            'quantidade' => '0.004',
        ])->assertStatus(422)->assertJsonValidationErrors('quantidade');

        $this->assertEquals(10, (float) Stock::where('product_id', $p->id)
            ->where('warehouse_id', $this->armazem->id)->value('quantity'));

        $this->assertNull(
            Stock::where('product_id', $p->id)->where('warehouse_id', $destino->id)->first(),
            'não pode ficar uma linha fantasma no armazém de destino'
        );
    }
}
