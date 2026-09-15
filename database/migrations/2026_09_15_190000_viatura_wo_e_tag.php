<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O WO# E O TAG# DA VIATURA (15/09/2026).
 *
 * Pedido: «na viatura adiciona esses 2 campos, folha de obra = WO e TAG, e deve
 * aparecer na listagem». São os dois números escritos no papel que acompanha o
 * carro na oficina — o da folha de obra (WO#: 1050186) e o da etiqueta da chave
 * (TAG#: 42). Texto livre: não se geram nem têm de ser únicos (a etiqueta volta
 * a pendurar-se noutro carro quando este sai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_vehicles', 'work_order_ref')) {
                $table->string('work_order_ref', 40)->nullable()->after('vehicle_number');
            }
            if (! Schema::hasColumn('workshop_vehicles', 'tag_number')) {
                $table->string('tag_number', 20)->nullable()->after('work_order_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            $table->dropColumn(['work_order_ref', 'tag_number']);
        });
    }
};
