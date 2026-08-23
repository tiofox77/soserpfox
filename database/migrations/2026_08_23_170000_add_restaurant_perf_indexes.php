<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compostos para as queries quentes do restaurante.
 *
 * As colunas de FK já têm índice de coluna única (automático do MySQL); estes
 * são os COMPOSTOS que faltam para as verificações mais repetidas:
 *  - "esta mesa tem conta aberta?"  (restaurant_orders)
 *  - conflito de reservas por mesa  (restaurant_reservations)
 *  - bilhetes de cozinha por conta  (restaurant_kitchen_tickets)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_orders')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                if (!$this->temIndice('restaurant_orders', 'restaurant_orders_table_status_index')) {
                    $table->index(['tenant_id', 'table_id', 'status'], 'restaurant_orders_table_status_index');
                }
            });
        }

        if (Schema::hasTable('restaurant_reservations')) {
            Schema::table('restaurant_reservations', function (Blueprint $table) {
                if (!$this->temIndice('restaurant_reservations', 'restaurant_reservation_conflict_index')) {
                    $table->index(['tenant_id', 'table_id', 'status', 'reserved_at'], 'restaurant_reservation_conflict_index');
                }
            });
        }

        if (Schema::hasTable('restaurant_kitchen_tickets')) {
            Schema::table('restaurant_kitchen_tickets', function (Blueprint $table) {
                if (!$this->temIndice('restaurant_kitchen_tickets', 'restaurant_kitchen_tickets_order_status_index')) {
                    $table->index(['tenant_id', 'order_id', 'status'], 'restaurant_kitchen_tickets_order_status_index');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            ['restaurant_orders', 'restaurant_orders_table_status_index'],
            ['restaurant_reservations', 'restaurant_reservation_conflict_index'],
            ['restaurant_kitchen_tickets', 'restaurant_kitchen_tickets_order_status_index'],
        ] as [$tabela, $indice]) {
            if (Schema::hasTable($tabela) && $this->temIndice($tabela, $indice)) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropIndex($indice));
            }
        }
    }

    private function temIndice(string $tabela, string $indice): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$tabela}`"))
            ->pluck('Key_name')->contains($indice);
    }
};
