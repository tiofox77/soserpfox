<?php

namespace App\Observers;

use App\Models\Invoicing\Stock;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Observer do Stock (invoicing_stocks).
 *
 * Mantém o agregado `invoicing_products.stock_quantity` SEMPRE igual à soma
 * das linhas em invoicing_stocks (por tenant + produto). É a defesa que
 * garante que nunca mais há divergência entre "Gestão de Stock" e POS.
 *
 * Idempotente — pode correr em massa sem efeitos colaterais.
 */
class StockObserver
{
    public function saved(Stock $stock): void
    {
        $this->syncAggregate($stock);
    }

    public function deleted(Stock $stock): void
    {
        $this->syncAggregate($stock);
    }

    protected function syncAggregate(Stock $stock): void
    {
        if (!$stock->product_id || !$stock->tenant_id) {
            return;
        }

        // Soma actual de TODAS as linhas para este produto/tenant
        $sum = (float) DB::table('invoicing_stocks')
            ->where('tenant_id', $stock->tenant_id)
            ->where('product_id', $stock->product_id)
            ->sum('quantity');

        // Atualiza directamente para evitar disparar Product save events
        DB::table('invoicing_products')
            ->where('id', $stock->product_id)
            ->where('tenant_id', $stock->tenant_id)
            ->update(['stock_quantity' => $sum]);
    }
}
