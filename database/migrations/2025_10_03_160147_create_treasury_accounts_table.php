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
        Schema::create('treasury_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_id')->constrained('treasury_banks')->onDelete('restrict');
            $table->string('account_name'); // Nome da conta
            $table->string('account_number'); // Número da conta
            $table->string('iban')->nullable(); // IBAN internacional
            $table->string('currency')->default('AOA'); // AOA, USD, EUR
            $table->string('account_type')->default('checking'); // checking, savings, investment
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('manager_name')->nullable(); // Gestor da conta
            $table->string('manager_phone')->nullable();
            $table->string('manager_email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Conta padrão
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
            $table->index('account_number');
            $table->index(['currency', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_accounts');
    }
};
