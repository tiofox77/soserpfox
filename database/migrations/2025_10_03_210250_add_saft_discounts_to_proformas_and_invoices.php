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
        // Sales Proformas
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            $table->decimal('discount_commercial', 15, 2)->default(0)->after('discount_amount')->comment('Desconto Comercial (antes IVA)');
            $table->decimal('discount_financial', 15, 2)->default(0)->after('discount_commercial')->comment('Desconto Financeiro (após IVA)');
        });

        // Sales Proforma Items
        Schema::table('invoicing_sales_proforma_items', function (Blueprint $table) {
            $table->decimal('discount_commercial_percent', 5, 2)->default(0)->after('discount_percent')->comment('% Desconto Comercial');
            $table->decimal('discount_commercial_amount', 15, 2)->default(0)->after('discount_commercial_percent')->comment('Valor Desconto Comercial');
        });

        // Sales Invoices
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->decimal('discount_commercial', 15, 2)->default(0)->after('discount_amount')->comment('Desconto Comercial (antes IVA)');
            $table->decimal('discount_financial', 15, 2)->default(0)->after('discount_commercial')->comment('Desconto Financeiro (após IVA)');
        });

        // Sales Invoice Items
        Schema::table('invoicing_sales_invoice_items', function (Blueprint $table) {
            $table->decimal('discount_commercial_percent', 5, 2)->default(0)->after('discount_percent')->comment('% Desconto Comercial');
            $table->decimal('discount_commercial_amount', 15, 2)->default(0)->after('discount_commercial_percent')->comment('Valor Desconto Comercial');
        });

        // Purchase Proformas
        Schema::table('invoicing_purchase_proformas', function (Blueprint $table) {
            $table->decimal('discount_commercial', 15, 2)->default(0)->after('discount_amount')->comment('Desconto Comercial (antes IVA)');
            $table->decimal('discount_financial', 15, 2)->default(0)->after('discount_commercial')->comment('Desconto Financeiro (após IVA)');
        });

        // Purchase Proforma Items
        Schema::table('invoicing_purchase_proforma_items', function (Blueprint $table) {
            $table->decimal('discount_commercial_percent', 5, 2)->default(0)->after('discount_percent')->comment('% Desconto Comercial');
            $table->decimal('discount_commercial_amount', 15, 2)->default(0)->after('discount_commercial_percent')->comment('Valor Desconto Comercial');
        });

        // Purchase Invoices
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->decimal('discount_commercial', 15, 2)->default(0)->after('discount_amount')->comment('Desconto Comercial (antes IVA)');
            $table->decimal('discount_financial', 15, 2)->default(0)->after('discount_commercial')->comment('Desconto Financeiro (após IVA)');
        });

        // Purchase Invoice Items
        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('discount_commercial_percent', 5, 2)->default(0)->after('discount_percent')->comment('% Desconto Comercial');
            $table->decimal('discount_commercial_amount', 15, 2)->default(0)->after('discount_commercial_percent')->comment('Valor Desconto Comercial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial', 'discount_financial']);
        });

        Schema::table('invoicing_sales_proforma_items', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial_percent', 'discount_commercial_amount']);
        });

        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial', 'discount_financial']);
        });

        Schema::table('invoicing_sales_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial_percent', 'discount_commercial_amount']);
        });

        Schema::table('invoicing_purchase_proformas', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial', 'discount_financial']);
        });

        Schema::table('invoicing_purchase_proforma_items', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial_percent', 'discount_commercial_amount']);
        });

        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial', 'discount_financial']);
        });

        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['discount_commercial_percent', 'discount_commercial_amount']);
        });
    }
};
