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
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->enum('status', ['draft', 'reconciled', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable(); // MT940, CSV, OFX
            $table->foreignId('reconciled_by')->nullable()->constrained('users');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'account_id', 'statement_date']);
        });
        
        // Tabela de itens de reconciliação
        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['debit', 'credit']);
            $table->enum('status', ['unmatched', 'matched', 'excluded'])->default('unmatched');
            $table->foreignId('move_line_id')->nullable()->constrained('accounting_move_lines')->nullOnDelete();
            $table->decimal('match_confidence', 5, 2)->nullable(); // %
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_items');
        Schema::dropIfExists('bank_reconciliations');
    }
};
