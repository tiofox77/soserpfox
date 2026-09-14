<?php

namespace Tests\Feature;

use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Tests\TenantTestCase;

class DiagnosticarMovimentosSemLoteTest extends TenantTestCase
{
    /** O diagnóstico corre e mostra o movimento sem lote — e não grava nada. @test */
    public function mostra_os_movimentos_sem_lote(): void
    {
        $armazem = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Loja', 'code' => 'LJ', 'is_active' => true]);
        $artigo = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Artigo sem lote', 'type' => 'produto', 'price' => 10, 'unit' => 'UN', 'is_active' => true]);

        StockMovement::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $armazem->id, 'product_id' => $artigo->id, 'type' => 'adjustment', 'quantity' => 5, 'user_id' => $this->user->id]);
        $antes = StockMovement::count();

        $this->artisan('stock:movimentos-sem-lote', ['--empresa' => $this->tenant->id])
            ->expectsOutputToContain('Artigo sem lote')
            ->assertSuccessful();

        $this->assertSame($antes, StockMovement::count());
    }
}
