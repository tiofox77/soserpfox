<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('receipt_number')->unique();
            
            // Tipo: venda ou compra
            $table->enum('type', ['sale', 'purchase'])->default('sale');
            
            // Referências (nullable pois pode ser recibo genérico)
            $table->foreignId('invoice_id')->nullable()->constrained('invoicing_sales_invoices')->onDelete('set null');
            $table->foreignId('client_id')->nullable()->constrained('invoicing_clients')->onDelete('restrict');
            $table->foreignId('supplier_id')->nullable()->constrained('invoicing_suppliers')->onDelete('restrict');
            
            // Pagamento
            $table->date('payment_date');
            $table->string('payment_method')->default('cash');
            $table->decimal('amount_paid', 15, 2);
            
            // Referências bancárias
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            
            // Status
            $table->enum('status', ['issued', 'cancelled'])->default('issued');
            
            // SAFT-AO Angola
            $table->string('saft_hash')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'payment_date']);
            $table->index(['type', 'status']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_receipts');
    }
};
