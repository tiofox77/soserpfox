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
        Schema::table('treasury_payment_methods', function (Blueprint $table) {
            // Remover a constraint unique do campo 'code'
            $table->dropUnique(['code']);
            
            // Adicionar constraint unique composta (tenant_id + code)
            $table->unique(['tenant_id', 'code'], 'treasury_payment_methods_tenant_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_payment_methods', function (Blueprint $table) {
            // Remover constraint composta
            $table->dropUnique('treasury_payment_methods_tenant_code_unique');
            
            // Restaurar constraint unique no campo 'code'
            $table->unique('code');
        });
    }
};
