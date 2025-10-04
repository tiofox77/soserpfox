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
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            // Verificar se coluna 'hash' existe e remover (duplicada)
            if (Schema::hasColumn('invoicing_sales_proformas', 'hash')) {
                $table->dropColumn('hash');
            }
            
            // Modificar saft_hash para TEXT (suporta hash RSA-2048 em Base64)
            $table->text('saft_hash')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            // Reverter para string(172)
            $table->string('saft_hash', 172)->nullable()->change();
            
            // Recriar coluna hash
            $table->string('hash', 172)->nullable()->after('notes');
        });
    }
};
