<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_debit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('debit_note_number')->unique();
            
            // Referência à fatura original
            $table->foreignId('invoice_id')->nullable()->constrained('invoicing_sales_invoices')->onDelete('set null');
            $table->foreignId('client_id')->constrained('invoicing_clients')->onDelete('restrict');
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->onDelete('set null');
            
            // Datas
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            
            // Motivo
            $table->enum('reason', ['interest', 'penalty', 'additional_charge', 'correction', 'other'])->default('additional_charge');
            $table->text('notes')->nullable();
            
            // Valores
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            
            // Status
            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled'])->default('draft');
            
            // SAFT-AO Angola
            $table->string('saft_hash')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'issue_date']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_debit_notes');
    }
};
