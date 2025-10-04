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
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('payment_method_id')->constrained('invoicing_sales_invoices')->onDelete('set null');
            $table->foreignId('purchase_id')->nullable()->after('invoice_id')->constrained('invoicing_purchase_invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['purchase_id']);
            $table->dropColumn(['invoice_id', 'purchase_id']);
        });
    }
};
