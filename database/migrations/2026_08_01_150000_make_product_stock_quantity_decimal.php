<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `invoicing_products.stock_quantity` passa de INT para decimal(15,3).
 *
 * As linhas de stock (`invoicing_stocks.quantity`) sempre foram decimal(15,3),
 * mas o agregado era inteiro. Como o StockObserver copia a soma das linhas para
 * o agregado, tudo o que fosse fraccionado era arredondado a cada sincronização:
 * 2,5 L de óleo apareciam como 2 ou 3.
 *
 * É especialmente visível na oficina (óleo, massa, fluido de travões) e no POS,
 * que lista pelo agregado — o ecrã mostrava uma quantidade e o armazém tinha
 * outra.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sem doctrine/dbal no projecto: SQL directo. `->change()` do Laravel
        // requer o dbal para alterações de tipo em MySQL.
        DB::statement('ALTER TABLE invoicing_products MODIFY stock_quantity DECIMAL(15,3) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoicing_products MODIFY stock_quantity INT NOT NULL DEFAULT 0');
    }
};
