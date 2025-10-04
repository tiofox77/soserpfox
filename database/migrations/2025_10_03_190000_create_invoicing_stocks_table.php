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
        Schema::create('invoicing_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('invoicing_warehouses')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('cascade');
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0); // Reservado para vendas
            $table->decimal('available_quantity', 15, 3)->default(0); // Disponível = quantity - reserved
            $table->decimal('unit_cost', 15, 2)->nullable(); // Custo médio
            $table->timestamps();

            // Unique: só pode ter 1 registro por produto/armazém
            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['tenant_id', 'warehouse_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_stocks');
    }
};
