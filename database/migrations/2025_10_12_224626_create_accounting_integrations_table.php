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
        Schema::create('accounting_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('module', ['sales', 'treasury', 'payroll', 'inventory']);
            $table->string('event', 50); // invoice, receipt, payment, etc
            $table->foreignId('debit_account_id')->nullable()->constrained('accounting_accounts');
            $table->foreignId('credit_account_id')->nullable()->constrained('accounting_accounts');
            $table->foreignId('journal_id')->constrained('accounting_journals');
            $table->json('conditions')->nullable(); // Condições específicas
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'module', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_integrations');
    }
};
