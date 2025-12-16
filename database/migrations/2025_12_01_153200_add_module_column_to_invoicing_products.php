<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona coluna 'module' para identificar o módulo de origem do serviço/produto
     */
    public function up(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->string('module', 50)->nullable()->after('type')->index()
                ->comment('Módulo de origem: salon, hotel, workshop, null=geral');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropColumn('module');
        });
    }
};
