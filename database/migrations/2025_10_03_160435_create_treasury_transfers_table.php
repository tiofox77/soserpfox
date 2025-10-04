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
        Schema::create('treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            
            // Origem
            $table->foreignId('from_account_id')->nullable()->constrained('treasury_accounts')->onDelete('restrict');
            $table->foreignId('from_cash_register_id')->nullable()->constrained('treasury_cash_registers')->onDelete('restrict');
            
            // Destino
            $table->foreignId('to_account_id')->nullable()->constrained('treasury_accounts')->onDelete('restrict');
            $table->foreignId('to_cash_register_id')->nullable()->constrained('treasury_cash_registers')->onDelete('restrict');
            
            $table->string('transfer_number')->unique(); // TRF-2025-0001
            $table->decimal('amount', 15, 2);
            $table->string('currency')->default('AOA');
            $table->decimal('fee', 10, 2)->default(0); // Taxa de transferência
            $table->date('transfer_date');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('completed'); // pending, completed, cancelled
            $table->string('reference')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['from_account_id', 'transfer_date']);
            $table->index(['to_account_id', 'transfer_date']);
            $table->index('transfer_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transfers');
    }
};
