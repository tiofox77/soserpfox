<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Reports\StockAdjustmentsReport;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\StockMovement;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Mapa de seguimento dos ajustes de stock.
 *
 * O que este relatório tem de garantir é o que o torna legível: mostra o que
 * foi mexido À MÃO e deixa de fora o que teve documento por trás. Um mapa de
 * controlo que se enche de movimentos de vendas deixa de servir para controlar.
 */
class StockAdjustmentsReportTest extends TenantTestCase
{
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

    public function test_o_ecra_abre(): void
    {
        $this->get(route('invoicing.reports.stock-adjustments'))
            ->assertOk()
            ->assertSee('Ajustes de Stock');
    }

    public function test_mostra_os_movimentos_manuais(): void
    {
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'notes' => 'Conferência de armazém']);

        Livewire::test(StockAdjustmentsReport::class)
            ->assertSee($p->name)
            ->assertSee('Conferência de armazém');
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

        Livewire::test(StockAdjustmentsReport::class)
            ->assertSee($manual->name)
            ->assertDontSee($venda->name);
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

        $componente = Livewire::test(StockAdjustmentsReport::class);
        $resumo     = $componente->viewData('resumo');

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

        Livewire::test(StockAdjustmentsReport::class)
            ->set('typeFilter', 'out')
            ->assertSee($b->name)
            ->assertDontSee($a->name);
    }

    public function test_a_procura_encontra_pela_referencia_do_lote(): void
    {
        $a = $this->produtoComStock(0);
        $b = $this->produtoComStock(0);

        $this->movimento(['product_id' => $a->id, 'batch_reference' => 'MOV/2026/000042']);
        $this->movimento(['product_id' => $b->id, 'batch_reference' => 'MOV/2026/000099']);

        Livewire::test(StockAdjustmentsReport::class)
            ->set('search', 'MOV/2026/000042')
            ->assertSee($a->name)
            ->assertDontSee($b->name);
    }

    public function test_o_periodo_exclui_o_que_esta_fora(): void
    {
        $dentro = $this->produtoComStock(0);
        $fora   = $this->produtoComStock(0);

        $this->movimento(['product_id' => $dentro->id]);

        $antigo = $this->movimento(['product_id' => $fora->id]);
        \Illuminate\Support\Facades\DB::table('invoicing_stock_movements')
            ->where('id', $antigo->id)
            ->update(['created_at' => now()->subYears(2)]);

        Livewire::test(StockAdjustmentsReport::class)
            ->assertSee($dentro->name)
            ->assertDontSee($fora->name);
    }

    public function test_o_ultimo_dia_do_periodo_entra_inteiro(): void
    {
        // `created_at` é timestamp: um whereBetween com a data seca cortava
        // tudo o que acontecesse depois da meia-noite do último dia.
        $p = $this->produtoComStock(0);

        $m = $this->movimento(['product_id' => $p->id]);
        \Illuminate\Support\Facades\DB::table('invoicing_stock_movements')
            ->where('id', $m->id)
            ->update(['created_at' => now()->endOfMonth()->setTime(23, 30)]);

        Livewire::test(StockAdjustmentsReport::class)
            ->set('period', 'month')
            ->assertSee($p->name);
    }

    public function test_a_perna_negativa_de_uma_transferencia_nao_falseia_os_totais(): void
    {
        // Uma transferência grava DUAS linhas e a que sai leva quantidade
        // negativa. Sem valor absoluto, uma soma de saídas podia sair negativa
        // e ler-se ao contrário do que é.
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_OUT, 'quantity' => -4]);

        $resumo = Livewire::test(StockAdjustmentsReport::class)->viewData('resumo');

        $this->assertEquals(4, $resumo['saidas_qtd'], 'a saída conta pelo valor absoluto');
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

        Livewire::test(StockAdjustmentsReport::class)
            ->assertDontSee('4.500.000,00');
    }

    public function test_o_resumo_por_operador_conta_quem_mexeu(): void
    {
        $p = $this->produtoComStock(0);

        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_IN, 'quantity' => 3]);
        $this->movimento(['product_id' => $p->id, 'type' => StockMovement::TYPE_ADJUSTMENT, 'quantity' => 50]);

        $linhas = Livewire::test(StockAdjustmentsReport::class)->viewData('porUtilizador');

        $this->assertCount(1, $linhas);
        $this->assertSame($this->user->name, $linhas->first()->nome);
        $this->assertEquals(2, $linhas->first()->n);
        $this->assertEquals(1, $linhas->first()->ajustes);
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

        Livewire::test(StockAdjustmentsReport::class)
            ->assertSee($meu->name)
            ->assertDontSee($artigoAlheio->name);
    }

    public function test_a_exportacao_devolve_csv_com_o_periodo_filtrado(): void
    {
        $p = $this->produtoComStock(0);
        $this->movimento(['product_id' => $p->id, 'notes' => 'Conferência anual']);

        // Chamada directa ao método: o wrapper de teste do Livewire não devolve
        // a resposta de um download, e o que interessa provar aqui é o conteúdo
        // do ficheiro, não o transporte.
        $componente = new StockAdjustmentsReport();
        $componente->mount();

        $resposta = $componente->exportarCsv();

        ob_start();
        $resposta->sendContent();
        $conteudo = ob_get_clean();

        $this->assertStringContainsString($p->name, $conteudo);
        $this->assertStringContainsString('Conferência anual', $conteudo);
        $this->assertStringContainsString('Operador', $conteudo);
    }
}
