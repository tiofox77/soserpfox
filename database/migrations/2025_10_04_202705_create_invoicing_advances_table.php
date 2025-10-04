<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('advance_number')->unique();
            
            // Tipo: venda (cliente paga antecipado) ou compra (pagamos antecipado a fornecedor)
            $table->enum('type', ['sale', 'purchase'])->default('sale');
            
            // Cliente ou Fornecedor
            $table->foreignId('client_id')->nullable()->constrained('invoicing_clients')->onDelete('restrict');
            $table->foreignId('supplier_id')->nullable()->constrained('invoicing_suppliers')->onDelete('restrict');
            
            // Pagamento
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('cash');
            
            // Finalidade
            $table->string('purpose')->nullable();
            $table->text('notes')->nullable();
            
            // Controle de uso
            $table->decimal('used_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            
            // Status
            $table->enum('status', ['available', 'partially_used', 'fully_used', 'refunded', 'cancelled'])->default('available');
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'payment_date']);
            $table->index(['type', 'status']);
            $table->index('client_id');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_advances');
    }
};
