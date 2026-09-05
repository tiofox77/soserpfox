<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A impressão automática dos talões da cozinha.
 *
 * O talão já existia como página — mas alguém tinha de abrir e carregar em
 * imprimir, a meio do serviço. Com isto ligado, o ecrã da cozinha imprime o
 * talão sozinho quando o bilhete chega.
 *
 * Desligado por omissão: liga-se no posto que TEM a impressora, e só nesse.
 * Ligado em todo o lado, cada ecrã aberto da cozinha imprimia a sua cópia do
 * mesmo talão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->boolean('kitchen_auto_print')->default(false)->after('use_kitchen_workflow');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn('kitchen_auto_print');
        });
    }
};
