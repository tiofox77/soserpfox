<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_order_item_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('restaurant_order_items')->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->constrained('invoicing_sales_invoices')->restrictOnDelete();
            $table->foreignId('sales_invoice_item_id')->constrained('invoicing_sales_invoice_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['tenant_id', 'order_item_id', 'sales_invoice_item_id'], 'restaurant_billing_line_unique');
            $table->index(['tenant_id', 'order_id', 'sales_invoice_id'], 'restaurant_billing_order_invoice_index');
        });

        Schema::create('restaurant_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->foreignId('sales_invoice_id')->nullable()->constrained('invoicing_sales_invoices')->nullOnDelete();
            $table->string('document_type', 2);
            $table->decimal('amount', 18, 2)->default(0);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key'], 'restaurant_checkout_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_payment_attempts');
        Schema::dropIfExists('restaurant_order_item_billings');
    }
};
