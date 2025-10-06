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
        Schema::create('invoicing_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Documento de venda
            $table->string('document_type'); // 'sales_invoice', 'sales_proforma', etc
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_item_id');
            
            // Lote alocado
            $table->foreignId('product_batch_id')->constrained('invoicing_product_batches')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('restrict');
            
            // Quantidade alocada
            $table->decimal('quantity_allocated', 15, 2);
            
            // Data de validade do lote (snapshot)
            $table->date('expiry_date_snapshot')->nullable();
            $table->string('batch_number_snapshot')->nullable();
            
            // Status
            $table->enum('status', ['allocated', 'confirmed', 'reverted'])->default('allocated');
            
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'document_type', 'document_id']);
            $table->index(['product_batch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_batch_allocations');
    }
};
