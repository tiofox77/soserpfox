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
        Schema::table('invoicing_clients', function (Blueprint $table) {
            // Alterar campo country para não ter default
            $table->string('country')->default('Angola')->change();
            
            // Adicionar campo logo
            $table->string('logo')->nullable()->after('nif');
            
            // Remover campo province antigo se existir e criar novo
            if (Schema::hasColumn('invoicing_clients', 'province')) {
                $table->dropColumn('province');
            }
        });
        
        // Adicionar campo province novamente
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->string('province')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->dropColumn(['logo', 'province']);
        });
    }
};
