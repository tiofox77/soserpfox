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
        Schema::create('invoicing_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('invoicing_warehouses')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('cascade');
            $table->enum('type', ['in', 'out', 'transfer', 'adjustment']); // entrada, saída, transferência, ajuste
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->string('reference_type')->nullable(); // 'purchase', 'sale', 'transfer', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable(); // ID da compra, venda, etc
            $table->foreignId('from_warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_stock_movements');
    }
};
