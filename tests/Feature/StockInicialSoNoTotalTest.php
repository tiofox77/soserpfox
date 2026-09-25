<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A migração que acerta o stock inicial que ficou só no total (25/09/2026).
 */
class StockInicialSoNoTotalTest extends TenantTestCase
{
    public function test_poe_a_quantidade_no_armazem_padrao_com_movimento_e_nao_repete(): void
    {
        $armazem = getOrCreateDefaultWarehouse()->id;
        $id = DB::table('invoicing_products')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'Tecno POP2 Ensaio', 'code' => 'T-' . uniqid(), 'type' => 'produto',
            'price' => 149000, 'cost' => 120500, 'unit' => 'UN', 'manage_stock' => true, 'is_active' => true,
            'stock_quantity' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migracao = require database_path('migrations/2026_09_25_100000_stock_inicial_que_ficou_so_no_total.php');
        $migracao->up();

        $linha = Stock::where('product_id', $id)->first();
        $this->assertNotNull($linha);
        $this->assertSame($armazem, (int) $linha->warehouse_id);
        $this->assertEqualsWithDelta(3, (float) $linha->quantity, 0.001);

        $entrada = StockMovement::where('product_id', $id)->first();
        $this->assertSame('in', $entrada->type);
        $this->assertEqualsWithDelta(3, (float) $entrada->balance_after, 0.001);
        $this->assertEqualsWithDelta(3, (float) Product::find($id)->stock_quantity, 0.001, 'o total não se toca');

        // Correr outra vez não faz nada.
        $migracao->up();
        $this->assertSame(1, Stock::where('product_id', $id)->count());
        $this->assertSame(1, StockMovement::where('product_id', $id)->count());
    }
}
