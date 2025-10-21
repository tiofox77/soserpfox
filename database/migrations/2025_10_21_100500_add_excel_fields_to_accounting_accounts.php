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
        Schema::table('accounting_accounts', function (Blueprint $table) {
            // IVA/Imposto padrão da conta
            $table->foreignId('default_tax_id')->nullable()->after('blocked')
                  ->constrained('accounting_taxes')->nullOnDelete()
                  ->comment('Imposto padrão (IVA) aplicado automaticamente');
            
            // Reflexão automática em Débito e Crédito
            $table->foreignId('debit_reflection_account_id')->nullable()->after('default_tax_id')
                  ->constrained('accounting_accounts')->nullOnDelete()
                  ->comment('Conta de contrapartida automática em débito');
                  
            $table->foreignId('credit_reflection_account_id')->nullable()->after('debit_reflection_account_id')
                  ->constrained('accounting_accounts')->nullOnDelete()
                  ->comment('Conta de contrapartida automática em crédito');
            
            // Centro de custo padrão
            $table->foreignId('default_cost_center_id')->nullable()->after('credit_reflection_account_id')
                  ->constrained('cost_centers')->nullOnDelete()
                  ->comment('Centro de custo padrão da conta');
            
            // Chave da conta (campo adicional identificador)
            $table->string('account_key', 50)->nullable()->after('default_cost_center_id')
                  ->comment('Chave/código adicional da conta');
            
            // Custo Fixo
            $table->boolean('is_fixed_cost')->default(false)->after('account_key')
                  ->comment('Indica se é custo fixo');
            
            // Tipo adicional (para classificações extras)
            $table->string('account_subtype', 50)->nullable()->after('is_fixed_cost')
                  ->comment('Subtipo ou classificação adicional');
            
            // Índices para performance
            $table->index('default_tax_id');
            $table->index('default_cost_center_id');
            $table->index('account_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->dropForeign(['default_tax_id']);
            $table->dropForeign(['debit_reflection_account_id']);
            $table->dropForeign(['credit_reflection_account_id']);
            $table->dropForeign(['default_cost_center_id']);
            
            $table->dropColumn([
                'default_tax_id',
                'debit_reflection_account_id',
                'credit_reflection_account_id',
                'default_cost_center_id',
                'account_key',
                'is_fixed_cost',
                'account_subtype',
            ]);
        });
    }
};
