<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('regime', 50)->default('regime_geral')->after('nif')
                ->comment('Regime Fiscal SAFT-AO 2025: regime_geral, regime_simplificado, regime_isencao, regime_nao_sujeicao, regime_misto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('regime');
        });
    }
};
