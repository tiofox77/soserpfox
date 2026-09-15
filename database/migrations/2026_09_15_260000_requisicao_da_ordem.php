<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AS PEÇAS EM FALTA VIRAM REQUISIÇÃO DE COMPRA (15/09/2026, OF-08).
 *
 * A requisição sabe de que ordem de serviço nasceu. Quando a encomenda que
 * saiu dela é recebida, a ordem fica a saber — e, se estava à espera de
 * peças, volta a «Em curso».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_requisicoes', function (Blueprint $table) {
            if (! Schema::hasColumn('compras_requisicoes', 'work_order_id')) {
                $table->foreignId('work_order_id')->nullable()->after('warehouse_id')->constrained('workshop_work_orders')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras_requisicoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
    }
};
