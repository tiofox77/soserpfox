<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga a factura ao orçamento que lhe deu origem.
 *
 * Espelha o proforma_id que já existe: quando um orçamento é convertido, a
 * factura guarda de onde veio. Nullable e onDelete set null — apagar o
 * orçamento não deve arrastar a factura fiscal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->foreignId('quote_id')
                ->nullable()
                ->after('proforma_id')
                ->constrained('invoicing_sales_quotes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
