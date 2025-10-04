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
        Schema::create('treasury_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained('treasury_accounts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('restrict'); // Responsável
            
            $table->string('reconciliation_number')->unique(); // REC-2025-0001
            $table->date('reconciliation_date');
            $table->date('statement_start_date'); // Início do extrato
            $table->date('statement_end_date'); // Fim do extrato
            
            $table->decimal('statement_balance', 15, 2); // Saldo do extrato bancário
            $table->decimal('system_balance', 15, 2); // Saldo no sistema
            $table->decimal('difference', 15, 2)->default(0); // Diferença
            
            $table->integer('total_transactions')->default(0);
            $table->integer('reconciled_transactions')->default(0);
            $table->integer('pending_transactions')->default(0);
            
            $table->string('status')->default('in_progress'); // in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->string('statement_file')->nullable(); // Arquivo do extrato
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['account_id', 'reconciliation_date']);
            $table->index('reconciliation_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliations');
    }
};
