<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\StockManagement;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use Livewire\Livewire;
use Tests\TenantTestCase;

class TmpTransferDecimalTest extends TenantTestCase
{
    public function test_transferencia_de_0_004(): void
    {
        $this->comPermissoes(
            'invoicing.stock.view',
            'invoicing.stock.edit',
            'invoicing.warehouse-transfer.create'
        )->comModulo('invoicing');

        $p = $this->produtoComStock(10);

        $destino = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Armazem Secundario',
            'code'       => 'SEC',
            'is_default' => false,
            'is_active'  => true,
        ]);

        $stock = Stock::where('product_id', $p->id)
            ->where('warehouse_id', $this->armazem->id)
            ->first();

        fwrite(STDERR, "\n[antes] origem qty = {$stock->quantity} / disponivel = {$stock->available_quantity}\n");

        $c = Livewire::test(StockManagement::class)
            ->call('openTransferModal', $stock->id)
            ->set('transferToWarehouse', $destino->id)
            ->set('transferQuantity', '0.004')
            ->call('saveTransfer');

        $c->assertHasNoErrors();

        $c->assertSessionHas('message', 'Transferência realizada com sucesso!');
        $c->assertSet('showTransferModal', false);

        $mov = StockMovement::where('type', 'transfer')->orderByDesc('id')->first();
        fwrite(STDERR, '[movimento] ' . var_export($mov?->only(['id', 'type', 'quantity', 'from_warehouse_id', 'to_warehouse_id']), true) . "\n");

        $origem = Stock::where('product_id', $p->id)->where('warehouse_id', $this->armazem->id)->first();
        $dest   = Stock::where('product_id', $p->id)->where('warehouse_id', $destino->id)->first();

        fwrite(STDERR, "[depois] origem qty = {$origem->quantity}\n");
        fwrite(STDERR, '[depois] destino = ' . var_export($dest?->only(['id', 'quantity', 'available_quantity']), true) . "\n");

        $this->assertNotNull($mov, 'movimento de transferencia gravado');
        $this->assertEquals(0.0, (float) $mov->quantity, 'quantity gravada arredondada a 0.00');
        $this->assertEquals(10.0, (float) $origem->quantity, 'origem inalterada');
        $this->assertNotNull($dest, 'linha fantasma criada no destino');
        $this->assertEquals(0.0, (float) $dest->quantity, 'linha fantasma a zero');
    }
}
