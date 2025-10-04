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
        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('restrict'); // Usuário responsável
            $table->foreignId('account_id')->nullable()->constrained('treasury_accounts')->onDelete('restrict'); // Conta bancária
            $table->foreignId('cash_register_id')->nullable()->constrained('treasury_cash_registers')->onDelete('restrict'); // Caixa
            $table->foreignId('payment_method_id')->constrained('treasury_payment_methods')->onDelete('restrict');
            
            // Relacionamento polimórfico para integração com documentos
            $table->morphs('related'); // related_id, related_type (SalesInvoice, PurchaseInvoice, etc)
            
            $table->string('transaction_number')->unique(); // TRX-2025-0001
            $table->string('type'); // income, expense, transfer
            $table->string('category')->nullable(); // sale, purchase, salary, rent, etc
            $table->decimal('amount', 15, 2);
            $table->string('currency')->default('AOA');
            $table->date('transaction_date');
            $table->string('reference')->nullable(); // Referência externa
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('completed'); // pending, completed, cancelled
            $table->boolean('is_reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->string('attachment')->nullable(); // Comprovativo
            $table->timestamps();
            
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['transaction_date', 'type']);
            $table->index(['account_id', 'transaction_date']);
            $table->index(['cash_register_id', 'transaction_date']);
            $table->index('transaction_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
