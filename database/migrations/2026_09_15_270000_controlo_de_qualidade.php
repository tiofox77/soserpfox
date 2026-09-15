<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O CONTROLO DE QUALIDADE ANTES DE CONCLUIR (15/09/2026, OF-09).
 *
 * É uma inspecção digital de outro tipo: o teste de estrada, as luzes, as
 * fugas, a limpeza. Um modelo de controlo de qualidade marcado «obrigatório»
 * impede a ordem de passar a Concluída sem ele feito e sem nada urgente.
 * Os modelos e inspecções que já existem ficam do tipo «inspecção».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_inspection_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_inspection_templates', 'kind')) {
                $table->string('kind', 20)->default('inspecao')->after('name');
                $table->boolean('is_required')->default(false)->after('is_default');
            }
        });

        Schema::table('workshop_work_order_inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_work_order_inspections', 'kind')) {
                $table->string('kind', 20)->default('inspecao')->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_inspection_templates', function (Blueprint $table) {
            $table->dropColumn(['kind', 'is_required']);
        });
        Schema::table('workshop_work_order_inspections', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
