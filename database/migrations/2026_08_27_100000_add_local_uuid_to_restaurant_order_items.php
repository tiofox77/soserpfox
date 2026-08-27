<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O identificador local do artigo da comanda.
 *
 * A comanda já tinha o seu (restaurant_orders.local_uuid, único por empresa),
 * mas os artigos não. Sem isto, um reenvio da mesma comanda offline — que
 * acontece sempre que a rede oscila a meio — voltava a lançar as linhas todas:
 * a mesa pedia dois pratos e a conta saía com quatro.
 *
 * Único por EMPRESA, não global: o identificador nasce no dispositivo e duas
 * empresas diferentes podem, em teoria, gerar o mesmo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->char('local_uuid', 36)->nullable()->after('order_id');
            $table->unique(['tenant_id', 'local_uuid'], 'restaurant_order_items_tenant_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->dropUnique('restaurant_order_items_tenant_uuid_unique');
            $table->dropColumn('local_uuid');
        });
    }
};
