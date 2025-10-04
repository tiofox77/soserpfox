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
        Schema::create('invoicing_purchase_proformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('proforma_number')->unique();
            $table->foreignId('supplier_id')->constrained('invoicing_suppliers')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            $table->date('proforma_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('AOA');
            $table->decimal('exchange_rate', 10, 4)->default(1);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoicing_purchase_proforma_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_proforma_id')->constrained('invoicing_purchase_proformas')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('restrict');
            $table->string('description')->nullable();
            $table->decimal('quantity', 15, 2);
            $table->string('unit', 20)->default('UN');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(14); // IVA padrão Angola
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('total', 15, 2);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_purchase_proforma_items');
        Schema::dropIfExists('invoicing_purchase_proformas');
    }
};
