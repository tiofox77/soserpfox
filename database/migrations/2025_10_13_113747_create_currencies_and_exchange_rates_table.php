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
        // Moedas
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // USD, EUR, AOA
            $table->string('name');
            $table->string('symbol', 10);
            $table->integer('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Taxas de câmbio
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_from_id')->constrained('currencies');
            $table->foreignId('currency_to_id')->constrained('currencies');
            $table->date('date');
            $table->decimal('rate', 15, 6);
            $table->string('source')->nullable(); // BNA, manual, API
            $table->timestamps();
            
            $table->unique(['currency_from_id', 'currency_to_id', 'date']);
        });
        
        // Adicionar campos multi-moeda nas tabelas existentes
        Schema::table('accounting_moves', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('tenant_id')->constrained('currencies');
            $table->decimal('exchange_rate', 15, 6)->nullable()->after('currency_id');
        });
        
        Schema::table('accounting_move_lines', function (Blueprint $table) {
            $table->decimal('amount_currency', 15, 2)->nullable()->after('credit');
            $table->foreignId('currency_id')->nullable()->after('amount_currency')->constrained('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_move_lines', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['amount_currency', 'currency_id']);
        });
        
        Schema::table('accounting_moves', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'exchange_rate']);
        });
        
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
