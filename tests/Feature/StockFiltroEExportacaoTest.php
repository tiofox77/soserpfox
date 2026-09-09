<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O FILTRO DE EXISTÊNCIA E O MAPA DE STOCK.
 *
 * «Mostrar tudo sem stock» só é útil se incluir o artigo que NUNCA entrou em
 * armazém nenhum — esse não tem linha em `invoicing_stocks` e era o único que
 * a lista jamais mostrava, sendo o mais em falta de todos.
 *
 * Por isso a pergunta muda de tabela: «o que há» é à prateleira, «o que falta»
 * é ao catálogo, com o stock à esquerda.
 *
 * E O PAPEL E O EXCEL SAEM COM OS MESMOS FILTROS DO ECRÃ. Quem imprime está a
 * conferir a prateleira contra aquilo que estava a ver; um mapa que mostrasse
 * outra coisa era pior do que não haver mapa.
 */
class StockFiltroEExportacaoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/stock';

    protected function setUp(): void
    {
        parent::setUp();

        // `$this->armazem` é o armazém por omissão que a base monta.
        $this->comModulo('invoicing')->comPermissoes('invoicing.stock.view');
    }

    private function artigo(string $nome, bool $gereStock = true): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => $nome,
            'code' => 'P-' . substr(uniqid(), -6),
            'price' => 1000,
            'cost' => 700,
            'type' => 'produto',
            'unit' => 'un',
            'is_active' => true,
            'manage_stock' => $gereStock,
            'stock_quantity' => 0,
        ]);
    }

    private function comStock(Product $p, float $quantidade): Stock
    {
        return Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $p->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => $quantidade,
            'available_quantity' => $quantidade,
            'unit_cost' => 700,
        ]);
    }

    /* ─── O filtro ────────────────────────────────────────────────────── */

    /**
     * O ARTIGO SEM LINHA NENHUMA É O QUE MAIS FALTA — e era o que não aparecia.
     */
    public function test_sem_stock_traz_o_artigo_que_nunca_entrou_em_armazem(): void
    {
        $nunca = $this->artigo('Nunca Entrou');
        $cheio = $this->artigo('Tem Stock');
        $this->comStock($cheio, 40);

        $r = $this->getJson(self::RAIZ . '?existencia=sem')->assertOk();

        $nomes = collect($r->json('data'))->pluck('artigo');

        $this->assertContains('Nunca Entrou', $nomes->all());
        $this->assertNotContains('Tem Stock', $nomes->all());

        // E vem sem linha de stock: não há o que ajustar nem transferir.
        $linha = collect($r->json('data'))->firstWhere('artigo', 'Nunca Entrou');
        $this->assertNull($linha['id']);
        $this->assertEquals(0, $linha['quantidade']);
        $this->assertSame($nunca->id, $linha['product_id']);
    }

    /** A linha a zero conta como em falta, tal como a que não existe. */
    public function test_sem_stock_traz_tambem_a_linha_a_zero(): void
    {
        $zerado = $this->artigo('Ficou a Zero');
        $this->comStock($zerado, 0);

        $nomes = collect($this->getJson(self::RAIZ . '?existencia=sem')->assertOk()->json('data'))->pluck('artigo');

        $this->assertContains('Ficou a Zero', $nomes->all());
    }

    /** Um serviço não «falta» em armazém nenhum. */
    public function test_quem_nao_gere_stock_fica_de_fora(): void
    {
        $this->artigo('Serviço de Montagem', gereStock: false);

        $nomes = collect($this->getJson(self::RAIZ . '?existencia=sem')->assertOk()->json('data'))->pluck('artigo');

        $this->assertNotContains('Serviço de Montagem', $nomes->all());
    }

    public function test_com_stock_deixa_de_fora_o_que_esta_a_zero(): void
    {
        $cheio = $this->artigo('Tem Stock');
        $zerado = $this->artigo('Ficou a Zero');
        $this->comStock($cheio, 40);
        $this->comStock($zerado, 0);

        $nomes = collect($this->getJson(self::RAIZ . '?existencia=com')->assertOk()->json('data'))->pluck('artigo');

        $this->assertContains('Tem Stock', $nomes->all());
        $this->assertNotContains('Ficou a Zero', $nomes->all());
    }

    /**
     * O ARMAZÃM ESCOLHIDO MUDA A PERGUNTA: «falta ALI», e não «falta na casa».
     */
    public function test_o_armazem_escolhido_muda_o_que_falta(): void
    {
        $outro = Warehouse::create([
            'tenant_id' => $this->tenant->id, 'code' => 'A2', 'name' => 'Armazém Dois', 'is_active' => true,
        ]);

        $artigo = $this->artigo('Só no Um');
        $this->comStock($artigo, 25);

        // No armazém Um tem; no Dois não.
        $noUm = collect($this->getJson(self::RAIZ . "?existencia=sem&armazem={$this->armazem->id}")->assertOk()->json('data'))->pluck('artigo');
        $noDois = collect($this->getJson(self::RAIZ . "?existencia=sem&armazem={$outro->id}")->assertOk()->json('data'))->pluck('artigo');

        $this->assertNotContains('Só no Um', $noUm->all());
        $this->assertContains('Só no Um', $noDois->all());
    }

    /** Sem filtro, a lista é a de sempre — as linhas que existem. */
    public function test_sem_filtro_a_lista_e_a_de_sempre(): void
    {
        $cheio = $this->artigo('Tem Stock');
        $this->comStock($cheio, 40);
        $this->artigo('Nunca Entrou');

        $nomes = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->pluck('artigo');

        $this->assertContains('Tem Stock', $nomes->all());
        $this->assertNotContains('Nunca Entrou', $nomes->all(), 'sem filtro, a lista é da prateleira');
    }

    /**
     * O CARTÃO E AS LINHAS DIZEM O MESMO.
     *
     * «0 abaixo do mínimo» por cima de sessenta linhas marcadas a vermelho é a
     * página a contradizer-se.
     */
    public function test_o_cartao_do_minimo_bate_com_as_linhas(): void
    {
        $this->artigo('Nunca Entrou');
        $this->artigo('Também Não');

        $r = $this->getJson(self::RAIZ . '?existencia=sem')->assertOk();

        $this->assertSame(2, $r->json('resumo.artigos'));
        $this->assertSame(2, $r->json('resumo.baixo'));
        $this->assertTrue(collect($r->json('data'))->every(fn ($l) => $l['baixo'] === true));
    }

    /* ─── O papel e o Excel ───────────────────────────────────────────── */

    public function test_o_papel_sai_com_os_filtros_escritos(): void
    {
        $this->artigo('Nunca Entrou');

        $this->get(route('invoicing.stock.imprimir', ['existencia' => 'sem', 'armazem' => $this->armazem->id]))
            ->assertOk()
            ->assertSee('Nunca Entrou')
            // O cabeçalho diz o que se pediu — sem isso ninguém confere o mapa
            // daqui a uma semana.
            ->assertSee($this->armazem->name)
            ->assertSee(__('Só sem existência'));
    }

    /** O mapa é o inventário INTEIRO, e não a página em que se estava. */
    public function test_o_papel_nao_e_paginado(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->artigo('Artigo ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $html = $this->get(route('invoicing.stock.imprimir', ['existencia' => 'sem']))->assertOk()->getContent();

        // A lista pagina de 15 em 15; o papel leva os vinte.
        $this->assertStringContainsString('Artigo 01', $html);
        $this->assertStringContainsString('Artigo 20', $html);
    }

    public function test_o_excel_descarrega(): void
    {
        $cheio = $this->artigo('Tem Stock');
        $this->comStock($cheio, 40);

        $r = $this->get(route('invoicing.stock.excel'))->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $r->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', $r->headers->get('Content-Disposition'));
    }

    /** E os dois pedem a permissão de ver stock — levam preços e existências. */
    public function test_o_papel_e_o_excel_pedem_permissao(): void
    {
        $semNada = \App\Models\User::create([
            'name' => 'Sem Nada', 'email' => 'sn' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $semNada->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->actingAs($semNada);

        $this->get(route('invoicing.stock.imprimir'))->assertForbidden();
        $this->get(route('invoicing.stock.excel'))->assertForbidden();
    }
}
