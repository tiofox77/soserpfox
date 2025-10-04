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
        Schema::create('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('purchase_order_id')->nullable()->constrained('invoicing_purchase_orders')->onDelete('set null');
            $table->string('invoice_number')->unique();
            $table->foreignId('supplier_id')->constrained('invoicing_suppliers')->onDelete('restrict');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft'); // draft, pending, paid, overdue, cancelled
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('currency')->default('AOA');
            $table->decimal('exchange_rate', 10, 4)->default(1);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['supplier_id', 'invoice_date']);
            $table->index('invoice_date');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_purchase_invoices');
    }
};
