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
        // IRT (Imposto sobre Rendimento do Trabalho) - Angola
        // Taxa padrão: 6.5% sobre serviços
        // Retido na fonte, aplicado antes do IVA
        
        // Sales Proformas
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            $table->decimal('irt_amount', 15, 2)->default(0)->after('tax_amount')->comment('IRT - Imposto Rendimento Trabalho (6.5%)');
            $table->boolean('is_service')->default(false)->after('status')->comment('Se é prestação de serviço (sujeito a IRT)');
        });

        // Sales Invoices
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->decimal('irt_amount', 15, 2)->default(0)->after('tax_payable')->comment('IRT - Imposto Rendimento Trabalho (6.5%)');
            $table->boolean('is_service')->default(false)->after('status')->comment('Se é prestação de serviço (sujeito a IRT)');
        });

        // Purchase Proformas
        Schema::table('invoicing_purchase_proformas', function (Blueprint $table) {
            $table->decimal('irt_amount', 15, 2)->default(0)->after('tax_amount')->comment('IRT - Imposto Rendimento Trabalho (6.5%)');
            $table->boolean('is_service')->default(false)->after('status')->comment('Se é prestação de serviço (sujeito a IRT)');
        });

        // Purchase Invoices
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->decimal('irt_amount', 15, 2)->default(0)->after('tax_amount')->comment('IRT - Imposto Rendimento Trabalho (6.5%)');
            $table->boolean('is_service')->default(false)->after('status')->comment('Se é prestação de serviço (sujeito a IRT)');
        });

        // Items - Identificar se linha é produto ou serviço
        Schema::table('invoicing_sales_proforma_items', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('order')->comment('Se é serviço (sujeito a IRT)');
            $table->decimal('irt_rate', 5, 2)->default(6.5)->after('is_service')->comment('Taxa IRT (%)');
            $table->decimal('irt_amount', 15, 2)->default(0)->after('irt_rate')->comment('Valor IRT retido');
        });

        Schema::table('invoicing_sales_invoice_items', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('order')->comment('Se é serviço (sujeito a IRT)');
            $table->decimal('irt_rate', 5, 2)->default(6.5)->after('is_service')->comment('Taxa IRT (%)');
            $table->decimal('irt_amount', 15, 2)->default(0)->after('irt_rate')->comment('Valor IRT retido');
        });

        Schema::table('invoicing_purchase_proforma_items', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('order')->comment('Se é serviço (sujeito a IRT)');
            $table->decimal('irt_rate', 5, 2)->default(6.5)->after('is_service')->comment('Taxa IRT (%)');
            $table->decimal('irt_amount', 15, 2)->default(0)->after('irt_rate')->comment('Valor IRT retido');
        });

        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('order')->comment('Se é serviço (sujeito a IRT)');
            $table->decimal('irt_rate', 5, 2)->default(6.5)->after('is_service')->comment('Taxa IRT (%)');
            $table->decimal('irt_amount', 15, 2)->default(0)->after('irt_rate')->comment('Valor IRT retido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            $table->dropColumn(['irt_amount', 'is_service']);
        });

        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['irt_amount', 'is_service']);
        });

        Schema::table('invoicing_purchase_proformas', function (Blueprint $table) {
            $table->dropColumn(['irt_amount', 'is_service']);
        });

        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['irt_amount', 'is_service']);
        });

        Schema::table('invoicing_sales_proforma_items', function (Blueprint $table) {
            $table->dropColumn(['is_service', 'irt_rate', 'irt_amount']);
        });

        Schema::table('invoicing_sales_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_service', 'irt_rate', 'irt_amount']);
        });

        Schema::table('invoicing_purchase_proforma_items', function (Blueprint $table) {
            $table->dropColumn(['is_service', 'irt_rate', 'irt_amount']);
        });

        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_service', 'irt_rate', 'irt_amount']);
        });
    }
};
