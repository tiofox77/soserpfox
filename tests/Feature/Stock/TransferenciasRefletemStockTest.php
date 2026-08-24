<?php

namespace Tests\Feature\Stock;

use App\Models\Invoicing\Stock;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Invoicing\Warehouse;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * As transferências têm de reflectir a quantidade NO MOMENTO — sem atraso.
 *
 * Há duas verdades a manter em passo: as linhas de `invoicing_stocks` (a fonte)
 * e o agregado `invoicing_products.stock_quantity`, que o StockObserver
 * recalcula. Se o agregado ficar para trás, o POS e as listas mostram
 * existências que já não há — e vende-se o que não existe.
 */
class TransferenciasRefletemStockTest extends TenantTestCase
{
    private function produto(string $nome = 'Artigo'): Product
    {
        return Product::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => $nome,
            'code'         => 'P' . uniqid(),
            'type'         => 'produto',
            'price'        => 1000,
            'unit'         => 'UN',
            'manage_stock' => true,
            'is_active'    => true,
            'category_id'  => DB::table('invoicing_categories')->where('tenant_id', $this->tenant->id)->value('id'),
        ]);
    }

    private function armazem(string $nome): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name'      => $nome,
            'code'      => strtoupper(substr(md5($nome . uniqid()), 0, 6)),
            'is_active' => true,
        ]);
    }

    /** Quantidade numa linha de stock (a fonte da verdade). */
    private function naLinha(int $armazemId, int $produtoId, ?int $tenantId = null): float
    {
        return (float) DB::table('invoicing_stocks')
            ->where('tenant_id', $tenantId ?? $this->tenant->id)
            ->where('warehouse_id', $armazemId)
            ->where('product_id', $produtoId)
            ->sum('quantity');
    }

    /** O agregado que os ecrãs mostram. */
    private function noAgregado(int $produtoId): float
    {
        return (float) DB::table('invoicing_products')->where('id', $produtoId)->value('stock_quantity');
    }

    public function test_entrada_de_stock_reflecte_no_agregado_de_imediato(): void
    {
        $p = $this->produto();
        $this->assertSame(0.0, $this->noAgregado($p->id));

        Stock::addStock($this->armazem->id, $p->id, 50);

        // Sem recarregar nada, sem esperar: o agregado tem de estar certo já.
        $this->assertSame(50.0, $this->naLinha($this->armazem->id, $p->id));
        $this->assertSame(50.0, $this->noAgregado($p->id), 'o agregado ficou atrasado face às linhas');
    }

    public function test_transferencia_entre_armazens_move_e_nao_altera_o_total(): void
    {
        $p = $this->produto();
        $origem  = $this->armazem;
        $destino = $this->armazem('Armazém B');

        Stock::addStock($origem->id, $p->id, 100);
        $totalAntes = $this->noAgregado($p->id);

        // É isto que a transferência faz por dentro: sai de um, entra no outro.
        Stock::removeStock($origem->id, $p->id, 30);
        Stock::addStock($destino->id, $p->id, 30);

        $this->assertSame(70.0, $this->naLinha($origem->id, $p->id), 'a origem não desceu');
        $this->assertSame(30.0, $this->naLinha($destino->id, $p->id), 'o destino não subiu');

        // Transferir NÃO cria nem destrói stock: o total da empresa é o mesmo.
        $this->assertSame($totalAntes, $this->noAgregado($p->id), 'o total mudou numa transferência interna');
        $this->assertSame(100.0, $this->noAgregado($p->id));
    }

    public function test_transferencia_inter_empresas_desce_numa_e_sobe_na_outra(): void
    {
        $origemProd = $this->produto('Artigo partilhado');
        Stock::addStock($this->armazem->id, $origemProd->id, 80);

        // Segunda empresa, com o seu próprio armazém e artigo.
        $outra = Tenant::create([
            'name' => 'Empresa B ' . uniqid(), 'nif' => '5000000000', 'is_active' => true,
        ]);
        $armazemB = Warehouse::create([
            'tenant_id' => $outra->id, 'name' => 'Central B',
            'code' => 'CB' . substr(uniqid(), -4), 'is_active' => true,
        ]);
        $produtoB = Product::withoutGlobalScope('tenant')->create([
            'tenant_id' => $outra->id, 'name' => 'Artigo partilhado', 'code' => 'PB' . uniqid(),
            'type' => 'produto', 'price' => 1000, 'unit' => 'UN',
            'manage_stock' => true, 'is_active' => true,
        ]);

        // O movimento inter-empresas: sai da A, entra na B.
        Stock::removeStock($this->armazem->id, $origemProd->id, 25);
        $linhaB = Stock::withoutGlobalScope('tenant')->create([
            'tenant_id' => $outra->id, 'warehouse_id' => $armazemB->id,
            'product_id' => $produtoB->id, 'quantity' => 25,
        ]);

        $this->assertSame(55.0, $this->naLinha($this->armazem->id, $origemProd->id), 'a empresa de origem não desceu');
        $this->assertSame(55.0, $this->noAgregado($origemProd->id), 'o agregado da origem ficou atrasado');

        // E na empresa de destino o agregado dela também tem de reflectir já.
        $this->assertSame(25.0, $this->naLinha($armazemB->id, $produtoB->id, $outra->id));
        $this->assertSame(25.0, $this->noAgregado($produtoB->id), 'o agregado do destino ficou atrasado');

        // O stock de uma empresa não pode aparecer na outra.
        $this->assertNotSame($origemProd->id, $produtoB->id);
    }

    public function test_varias_transferencias_seguidas_nao_acumulam_desvio(): void
    {
        $p = $this->produto();
        $a = $this->armazem;
        $b = $this->armazem('Armazém B');

        Stock::addStock($a->id, $p->id, 100);

        // Dez idas e voltas: se o agregado fosse mantido por soma/subtracção
        // em vez de recalculado, o erro acumulava-se aqui.
        for ($i = 0; $i < 10; $i++) {
            Stock::removeStock($a->id, $p->id, 5);
            Stock::addStock($b->id, $p->id, 5);
            Stock::removeStock($b->id, $p->id, 5);
            Stock::addStock($a->id, $p->id, 5);
        }

        $this->assertSame(100.0, $this->naLinha($a->id, $p->id));
        $this->assertSame(0.0, $this->naLinha($b->id, $p->id));
        $this->assertSame(100.0, $this->noAgregado($p->id), 'acumulou desvio ao fim de 10 transferências');
    }
}
