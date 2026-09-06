<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\StockMovement;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Mapa de seguimento dos ajustes de stock.
 *
 * O que este relatório tem de garantir é o que o torna legível: mostra o que
 * foi mexido À MÃO e deixa de fora o que teve documento por trás. Um mapa de
 * controlo que se enche de movimentos de vendas deixa de servir para controlar.
 *
 * O ecrã Livewire deu lugar ao ecrã genérico dos relatórios em React; o mapa é
 * hoje o `stock-adjustments` do catálogo (classe `Relatorios\AjustesDeStock`) e
 * chega ao ecrã por `/api/v1/invoicing/react/relatorios/stock-adjustments`. É a
 * essa porta que estes ensaios batem.
 */
class StockAdjustmentsReportTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/relatorios/stock-adjustments';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.reports.view', 'invoicing.stock.view', 'invoicing.stock.edit')
             ->comModulo('invoicing');
    }

    private function movimento(array $dados): StockMovement
    {
        return StockMovement::semAplicarStock(fn () => StockMovement::create(array_merge([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 5,
            'unit_cost'    => 100,
            'user_id'      => $this->user->id,
        ], $dados)));
    }

    /** Os dados do mapa, com os filtros do ecrã na query. */
    private function mapa(array $filtros = []): array
    {
        return $this->getJson(self::RAIZ . ($filtros ? '?' . http_build_query($filtros) : ''))
            ->assertOk()->json('dados');
    }

    /** Os artigos que o mapa mostra, na ordem em que os mostra. */
    private function artigosDoMapa(array $filtros = []): array
    {
        return array_values(array_filter(array_map(
            fn ($m) => $m['product']['name'] ?? null,
            $this->mapa($filtros)['movimentos']
        )));
    }

    public function test_o_ecra_abre(): void
    {
        // A página é a casca do React; o título e as colunas vêm do esquema.
        $this->get(route('invoicing.reports.stock-adjustments'))->assertOk();

        $this->getJson(self::RAIZ)->assertOk()
            ->assertJsonPath('esquema.titulo', 'Ajustes de Stock')
            ->assertJsonPath('esquema.slug', 'stock-adjustments');
    }

    public function test_mostra_os_movimentos_manuais(): void
    {
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'notes' => 'Conferência de armazém']);

        $movimentos = $this->mapa()['movimentos'];

        $this->assertCount(1, $movimentos);
        $this->assertSame($p->name, $movimentos[0]['product']['name']);
        $this->assertSame('Conferência de armazém', $movimentos[0]['notes']);
    }

    public function test_esconde_os_movimentos_com_documento_por_tras(): void
    {
        // Uma saída de venda tem factura e já aparece nos mapas de vendas. Se
        // entrasse aqui, o mapa de controlo ficava afogado no movimento normal
        // do negócio e deixava de servir para o que serve.
        $manual = $this->produtoComStock(0);
        $venda  = $this->produtoComStock(0);

        $this->movimento(['product_id' => $manual->id, 'notes' => 'Entrada manual']);

        $this->movimento([
            'product_id'     => $venda->id,
            'type'           => StockMovement::TYPE_OUT,
            'reference_type' => SalesInvoice::class,
            'reference_id'   => 1,
            'notes'          => 'Venda - Fatura FT 1/1',
        ]);

        $artigos = $this->artigosDoMapa();

        $this->assertContains($manual->name, $artigos);
        $this->assertNotContains($venda->name, $artigos);
    }

    public function test_os_totais_nao_somam_ajustes_nem_transferencias(): void
    {
        // Num ajuste, a quantidade é o saldo FINAL e não uma variação; uma
        // transferência muda o artigo de armazém sem alterar o que a empresa
        // tem. Somá-las às entradas dava um número sem significado.
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_IN, 'quantity' => 7]);
        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_ADJUSTMENT, 'quantity' => 999]);
        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_TRANSFER, 'quantity' => 500]);

        $resumo = $this->mapa()['resumo'];

        $this->assertEquals(7, $resumo['entradas_qtd'], 'só a entrada conta na quantidade');
        $this->assertSame(1, $resumo['ajustes_n']);
        $this->assertSame(1, $resumo['transferencias_n']);
    }

    public function test_os_filtros_estreitam_o_mapa(): void
    {
        $a = $this->produtoComStock(0);
        $b = $this->produtoComStock(0);

        $this->movimento(['product_id' => $a->id, 'type' => StockMovement::TYPE_IN]);
        $this->movimento(['product_id' => $b->id, 'type' => StockMovement::TYPE_OUT]);

        $artigos = $this->artigosDoMapa(['typeFilter' => 'out']);

        $this->assertContains($b->name, $artigos);
        $this->assertNotContains($a->name, $artigos);
    }

    public function test_a_procura_encontra_pela_referencia_do_lote(): void
    {
        $a = $this->produtoComStock(0);
        $b = $this->produtoComStock(0);

        $this->movimento(['product_id' => $a->id, 'batch_reference' => 'MOV/2026/000042']);
        $this->movimento(['product_id' => $b->id, 'batch_reference' => 'MOV/2026/000099']);

        $artigos = $this->artigosDoMapa(['search' => 'MOV/2026/000042']);

        $this->assertContains($a->name, $artigos);
        $this->assertNotContains($b->name, $artigos);
    }

    public function test_o_periodo_exclui_o_que_esta_fora(): void
    {
        $dentro = $this->produtoComStock(0);
        $fora   = $this->produtoComStock(0);

        $this->movimento(['product_id' => $dentro->id]);

        $antigo = $this->movimento(['product_id' => $fora->id]);
        DB::table('invoicing_stock_movements')
            ->where('id', $antigo->id)
            ->update(['created_at' => now()->subYears(2)]);

        $artigos = $this->artigosDoMapa();

        $this->assertContains($dentro->name, $artigos);
        $this->assertNotContains($fora->name, $artigos);
    }

    public function test_o_ultimo_dia_do_periodo_entra_inteiro(): void
    {
        // `created_at` é timestamp: um whereBetween com a data seca cortava
        // tudo o que acontecesse depois da meia-noite do último dia.
        $p = $this->produtoComStock(0);

        $m = $this->movimento(['product_id' => $p->id]);
        DB::table('invoicing_stock_movements')
            ->where('id', $m->id)
            ->update(['created_at' => now()->endOfMonth()->setTime(23, 30)]);

        $this->assertContains($p->name, $this->artigosDoMapa(['period' => 'month']));
    }

    public function test_a_perna_negativa_de_uma_transferencia_nao_falseia_os_totais(): void
    {
        // Uma transferência grava DUAS linhas e a que sai leva quantidade
        // negativa. Sem valor absoluto, uma soma de saídas podia sair negativa
        // e ler-se ao contrário do que é.
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_OUT, 'quantity' => -4]);

        $this->assertEquals(4, $this->mapa()['resumo']['saidas_qtd'], 'a saída conta pelo valor absoluto');
    }

    public function test_um_ajuste_nao_apresenta_valor_de_movimento(): void
    {
        // Num ajuste, quantidade × custo é a VALORIZAÇÃO das existências e não
        // o que se ajustou. Mostrá-la na coluna de valor fazia um ajuste de
        // inventário parecer um movimento de milhões.
        $p = $this->produtoComStock(0);

        $this->movimento([
            'product_id' => $p->id,
            'type'       => StockMovement::TYPE_ADJUSTMENT,
            'quantity'   => 10,
            'unit_cost'  => 450000,
        ]);

        $movimentos = $this->mapa()['movimentos'];

        $this->assertCount(1, $movimentos);
        $this->assertNull($movimentos[0]['valor'], 'um ajuste não tem valor de movimento para mostrar');
    }

    public function test_o_resumo_por_operador_conta_quem_mexeu(): void
    {
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_IN, 'quantity' => 3]);
        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_ADJUSTMENT, 'quantity' => 50]);

        $linhas = $this->mapa()['porUtilizador'];

        $this->assertCount(1, $linhas);
        $this->assertSame($this->user->name, $linhas[0]['nome']);
        $this->assertEquals(2, $linhas[0]['n']);
        $this->assertEquals(1, $linhas[0]['ajustes']);
    }

    public function test_o_mapa_de_uma_empresa_nao_mostra_o_da_outra(): void
    {
        $meu = $this->produtoComStock(0);
        $this->movimento(['product_id' => $meu->id]);

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $artigoAlheio = \App\Models\Product::create([
            'tenant_id' => $outra->id, 'name' => 'Artigo Alheio ' . uniqid(),
            'code' => 'AL' . strtoupper(substr(uniqid(), -6)),
            'type' => 'produto', 'price' => 100, 'cost' => 50, 'unit' => 'UN', 'is_active' => true,
        ]);

        $armazemAlheio = \App\Models\Invoicing\Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $outra->id)->first();

        StockMovement::semAplicarStock(fn () => StockMovement::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'warehouse_id' => $armazemAlheio->id,
            'product_id' => $artigoAlheio->id, 'type' => 'in', 'quantity' => 5,
            'unit_cost' => 10, 'user_id' => $this->user->id,
        ]));

        $artigos = $this->artigosDoMapa();

        $this->assertContains($meu->name, $artigos);
        $this->assertNotContains($artigoAlheio->name, $artigos);
    }

    public function test_a_exportacao_devolve_csv_com_o_periodo_filtrado(): void
    {
        $p = $this->produtoComStock(0);
        $this->movimento(['product_id' => $p->id, 'notes' => 'Conferência anual']);

        // Um movimento de há dois anos: não pode entrar nas contas do ficheiro.
        $antigo = $this->movimento(['product_id' => $p->id, 'quantity' => 500]);
        DB::table('invoicing_stock_movements')->where('id', $antigo->id)->update(['created_at' => now()->subYears(2)]);

        $resposta = $this->get('/invoicing/reports/stock-adjustments/csv');
        $resposta->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resposta->headers->get('content-type'));

        $conteudo = $resposta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo, 'o BOM para o Excel em português');
        $this->assertStringContainsString('Operador', $conteudo);
        $this->assertStringContainsString($this->user->name, $conteudo);
        // Um movimento no período — o de há dois anos ficou de fora (senão
        // seriam 2 movimentos e 505 de quantidade entrada).
        $this->assertStringContainsString('"' . $this->user->name . '";1;5,00;', $conteudo);

    }

    /**
     * O PAPEL QUE SE LEVA PARA O ARMAZÉM.
     *
     * O ecrã genérico exportava sempre a PRIMEIRA tabela do mapa — aqui o
     * resumo «Por operador». O que o ecrã em Livewire dava, e o que serve para
     * conferir prateleira a prateleira, é a LISTA DE MOVIMENTOS: artigo, nota
     * e documento. Sem escolher a tabela, esse papel deixou de existir.
     */
    public function test_a_exportacao_deixa_escolher_a_tabela(): void
    {
        $p = $this->produtoComStock(0);
        $this->movimento(['product_id' => $p->id, 'notes' => 'Conferência anual']);

        // Sem dizer qual, continua a ser a primeira: quem já tinha a ligação
        // de sempre não vê o ficheiro mudar debaixo dos pés.
        $primeira = $this->get('/invoicing/reports/stock-adjustments/csv');
        $primeira->assertOk();
        $this->assertStringContainsString('Operador', $primeira->streamedContent());

        $movimentos = $this->get('/invoicing/reports/stock-adjustments/csv?tabela=movimentos');
        $movimentos->assertOk();

        $conteudo = $movimentos->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo, 'o BOM para o Excel em português');
        $this->assertStringContainsString('Produto', $conteudo, 'a lista de movimentos tem o artigo');
        $this->assertStringContainsString('Documento', $conteudo, 'e o documento que o originou');
        $this->assertStringContainsString($p->name, $conteudo);
        $this->assertStringContainsString('Conferência anual', $conteudo, 'e a nota de quem contou');

        // O nome do ficheiro diz qual das tabelas é.
        $this->assertStringContainsString(
            'stock-adjustments_movimentos_',
            (string) $movimentos->headers->get('content-disposition')
        );

        // Uma tabela que não existe cai na primeira, não rebenta.
        $this->get('/invoicing/reports/stock-adjustments/csv?tabela=inventada')
            ->assertOk();
    }
}
