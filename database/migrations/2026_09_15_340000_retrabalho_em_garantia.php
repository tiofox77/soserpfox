<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O RETRABALHO EM GARANTIA (15/09/2026, OF-19).
 *
 * Uma ordem de garantia é uma ordem normal (linhas, peças que saem do stock,
 * tempos) ligada à ordem original, que NÃO se factura ao cliente e que os
 * indicadores contam à parte — com a causa (mão-de-obra, peça, diagnóstico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_work_orders', 'warranty_of_id')) {
                $table->foreignId('warranty_of_id')->nullable()->after('warranty_expires')->constrained('workshop_work_orders')->nullOnDelete();
                $table->string('warranty_cause', 20)->nullable()->after('warranty_of_id');
                $table->string('warranty_reason', 500)->nullable()->after('warranty_cause');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warranty_of_id');
            $table->dropColumn(['warranty_cause', 'warranty_reason']);
        });
    }
};
