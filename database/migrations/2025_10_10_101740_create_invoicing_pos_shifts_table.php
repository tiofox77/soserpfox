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
        // Tabela principal de turnos POS
        Schema::create('invoicing_pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Informações do turno
            $table->string('shift_number', 50)->unique();
            $table->enum('status', ['open', 'closed'])->default('open');
            
            // Datas e horários
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            
            // Valores iniciais
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->text('opening_notes')->nullable();
            
            // Valores do turno (calculados)
            $table->decimal('cash_sales', 15, 2)->default(0);
            $table->decimal('card_sales', 15, 2)->default(0);
            $table->decimal('bank_transfer_sales', 15, 2)->default(0);
            $table->decimal('other_sales', 15, 2)->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            
            // Contadores
            $table->integer('total_invoices')->default(0);
            $table->integer('total_receipts')->default(0);
            
            // Valores de fechamento
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            
            // Notas de fechamento
            $table->text('closing_notes')->nullable();
            $table->text('difference_reason')->nullable();
            
            // Auditoria
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opened_ip', 45)->nullable();
            $table->string('closed_ip', 45)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('opened_at', 'idx_opened_at');
        });
        
        // Tabela de transações do turno
        Schema::create('invoicing_pos_shift_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('invoicing_pos_shifts')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Tipo e referência
            $table->enum('type', ['invoice', 'receipt', 'adjustment', 'withdrawal', 'deposit']);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            
            // Valores
            $table->string('payment_method', 50);
            $table->decimal('amount', 15, 2);
            
            // Informações adicionais
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Índices com nomes curtos
            $table->index(['shift_id', 'type'], 'idx_shift_type');
            $table->index('payment_method', 'idx_payment');
            $table->index(['reference_type', 'reference_id'], 'idx_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_pos_shift_transactions');
        Schema::dropIfExists('invoicing_pos_shifts');
    }
};
