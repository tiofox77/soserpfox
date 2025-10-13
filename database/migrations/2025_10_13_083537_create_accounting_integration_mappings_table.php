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
        Schema::create('accounting_integration_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('event'); // 'invoice', 'receipt', 'purchase', 'payment', etc
            $table->foreignId('journal_id')->nullable()->constrained('accounting_journals')->onDelete('set null');
            $table->foreignId('debit_account_id')->nullable()->constrained('accounting_accounts')->onDelete('set null');
            $table->foreignId('credit_account_id')->nullable()->constrained('accounting_accounts')->onDelete('set null');
            $table->foreignId('vat_account_id')->nullable()->constrained('accounting_accounts')->onDelete('set null');
            $table->json('conditions')->nullable(); // Condições adicionais (ex: tipo de documento, categoria)
            $table->boolean('auto_post')->default(true); // Lançar automaticamente
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_integration_mappings');
    }
};
