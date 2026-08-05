<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Cada movimento grava o saldo antes e o saldo depois.
 *
 * A coluna "Saldo" do histórico vinha vazia em todas as linhas: só o ecrã de
 * ajustes preenchia balance_after — vendas, entradas e transferências
 * deixavam-no nulo. E sem o saldo anterior não se lê um ajuste, porque a sua
 * quantidade é o valor FINAL e não uma variação: "= 18" não diz se subiu ou
 * desceu, nem quanto.
 */
class SaldosDoMovimentoTest extends TenantTestCase
{
    protected Warehouse $armazem;
    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->armazem = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sala de Vendas',
            'code' => 'ARM-1',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->produto = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'AMIDOL Paracetamol 500mg',
            'sku' => 'AMI-500',
            'price' => 300,
            'cost' => 150,
            'type' => 'produto',
            'manage_stock' => true,
            'stock_quantity' => 0,
        ]);
    }

    private function comStock(float $q): Stock
    {
        return Stock::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $this->produto->id,
            'quantity' => $q,
        ]);
    }

    private function movimento(array $dados): StockMovement
    {
        return StockMovement::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $this->produto->id,
            'user_id' => $this->user->id,
        ], $dados));
    }

    public function test_uma_saida_grava_os_dois_saldos(): void
    {
        $this->comStock(20);

        $m = $this->movimento(['type' => 'out', 'quantity' => 3])->fresh();

        $this->assertSame(20.0, (float) $m->balance_before);
        $this->assertSame(17.0, (float) $m->balance_after);
    }

    public function test_uma_entrada_grava_os_dois_saldos(): void
    {
        $this->comStock(5);

        $m = $this->movimento(['type' => 'in', 'quantity' => 7, 'unit_cost' => 100])->fresh();

        $this->assertSame(5.0, (float) $m->balance_before);
        $this->assertSame(12.0, (float) $m->balance_after);
    }

    public function test_uma_venda_que_deixa_negativo_grava_o_negativo(): void
    {
        // O stock já foi descontado por quem vendeu; o movimento é escrito com
        // semAplicarStock. O saldo lido tem de ser o de DEPOIS, negativo
        // incluído — é o que mostra que se vendeu mais do que havia.
        $linha = $this->comStock(2);
        $linha->quantity = -3;   // vendeu 5
        $linha->save();

        $m = StockMovement::semAplicarStock(fn () => $this->movimento([
            'type' => 'out', 'quantity' => 5,
        ]))->fresh();

        $this->assertSame(-3.0, (float) $m->balance_after);
        $this->assertSame(2.0, (float) $m->balance_before);
    }

    public function test_num_ajuste_o_saldo_anterior_e_o_que_da_sentido(): void
    {
        // A quantidade de um ajuste é o valor final. Só com o saldo anterior é
        // que se sabe que 18 foi uma subida de 6 e não uma entrada de 18.
        $this->comStock(12);

        $m = $this->movimento(['type' => 'adjustment', 'quantity' => 18, 'balance_before' => 12])->fresh();

        $this->assertSame(12.0, (float) $m->balance_before);
        $this->assertSame(18.0, (float) $m->balance_after);
        $this->assertSame(
            6.0,
            (float) $m->balance_after - (float) $m->balance_before,
            'a variação verdadeira do ajuste'
        );
    }

    public function test_o_saldo_carimbado_nao_reaplica_o_stock(): void
    {
        // carimbarSaldos() grava o movimento outra vez. Com save() normal, o
        // hook `created`... não volta a correr, mas um `saved` mal posto
        // reaplicaria o stock. Este teste fixa que o stock fica como está.
        $this->comStock(10);

        $this->movimento(['type' => 'out', 'quantity' => 4]);

        $this->assertSame(
            6.0,
            (float) Stock::where('product_id', $this->produto->id)->first()->quantity,
            'o stock só pode ter descido uma vez'
        );
    }

    public function test_movimentos_antigos_ficam_sem_saldo_e_nao_se_inventa(): void
    {
        // Os movimentos anteriores a estas colunas não têm saldo nenhum, e
        // reconstruí-lo para trás daria um número com ar de registo — a
        // importação inicial escreveu 27 mil linhas de stock sem movimentos,
        // por isso qualquer reconstrução seria falsa antes dessa data.
        $this->comStock(10);
        $m = $this->movimento(['type' => 'out', 'quantity' => 2]);

        \Illuminate\Support\Facades\DB::table('invoicing_stock_movements')
            ->where('id', $m->id)
            ->update(['balance_before' => null, 'balance_after' => null]);

        $this->assertNull($m->fresh()->balance_after);
    }
}
