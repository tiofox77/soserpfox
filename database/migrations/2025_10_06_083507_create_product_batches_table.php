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
        Schema::create('invoicing_product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            
            // Informações do lote
            $table->string('batch_number')->nullable()->comment('Número do lote/série');
            $table->date('manufacturing_date')->nullable()->comment('Data de fabricação');
            $table->date('expiry_date')->nullable()->comment('Data de validade');
            
            // Quantidade
            $table->decimal('quantity', 15, 2)->default(0)->comment('Quantidade no lote');
            $table->decimal('quantity_available', 15, 2)->default(0)->comment('Quantidade disponível');
            
            // Origem
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('invoicing_purchase_invoices')->onDelete('set null');
            $table->string('supplier_name')->nullable();
            
            // Preços
            $table->decimal('cost_price', 15, 2)->default(0)->comment('Preço de custo unitário');
            
            // Status
            $table->enum('status', ['active', 'expired', 'sold_out'])->default('active');
            
            // Alertas
            $table->integer('alert_days')->default(30)->comment('Dias antes da validade para alertar');
            
            // Notas
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'product_id', 'expiry_date']);
            $table->index(['tenant_id', 'warehouse_id', 'status']);
            $table->index(['expiry_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_product_batches');
    }
};
