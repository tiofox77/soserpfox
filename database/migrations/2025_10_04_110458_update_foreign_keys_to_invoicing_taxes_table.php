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
        // Atualizar tabela invoicing_products
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_taxes')->onDelete('set null');
        });

        // Atualizar tabela invoicing_purchase_order_items
        Schema::table('invoicing_purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_taxes')->onDelete('set null');
        });

        // Atualizar tabela invoicing_sales_proforma_items
        Schema::table('invoicing_sales_proforma_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_taxes')->onDelete('set null');
        });

        // Atualizar tabela invoicing_sales_invoice_items
        Schema::table('invoicing_sales_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_taxes')->onDelete('set null');
        });

        // Atualizar tabela invoicing_purchase_invoice_items
        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_taxes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para invoicing_tax_rates (caso precise)
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_tax_rates')->onDelete('set null');
        });

        Schema::table('invoicing_purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_tax_rates')->onDelete('set null');
        });

        Schema::table('invoicing_sales_proforma_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_tax_rates')->onDelete('set null');
        });

        Schema::table('invoicing_sales_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_tax_rates')->onDelete('set null');
        });

        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->foreign('tax_rate_id')->references('id')->on('invoicing_tax_rates')->onDelete('set null');
        });
    }
};
