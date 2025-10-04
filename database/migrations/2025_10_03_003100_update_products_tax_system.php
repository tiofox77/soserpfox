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
        Schema::table('invoicing_products', function (Blueprint $table) {
            // Remover campos antigos de IVA se existirem
            if (Schema::hasColumn('invoicing_products', 'is_iva_subject')) {
                $table->dropColumn('is_iva_subject');
            }
            if (Schema::hasColumn('invoicing_products', 'iva_rate')) {
                $table->dropColumn('iva_rate');
            }
            if (Schema::hasColumn('invoicing_products', 'iva_reason')) {
                $table->dropColumn('iva_reason');
            }
            if (Schema::hasColumn('invoicing_products', 'tax_rate')) {
                $table->dropColumn('tax_rate');
            }
        });
        
        Schema::table('invoicing_products', function (Blueprint $table) {
            // Novo sistema de taxas
            $table->enum('tax_type', ['iva', 'isento'])->default('iva')->after('cost');
            $table->foreignId('tax_rate_id')->nullable()->after('tax_type')->constrained('invoicing_tax_rates')->onDelete('set null');
            $table->string('exemption_reason')->nullable()->after('tax_rate_id');
            
            // Índice
            $table->index('tax_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['tax_type', 'tax_rate_id', 'exemption_reason']);
        });
    }
};
