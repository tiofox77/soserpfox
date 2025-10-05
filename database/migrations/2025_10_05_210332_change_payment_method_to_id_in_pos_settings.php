<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Muda pos_default_payment_method (string) para pos_default_payment_method_id (foreignId)
     * para usar métodos de pagamento da Tesouraria
     */
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            // Verificar se coluna antiga existe antes de remover
            if (Schema::hasColumn('invoicing_settings', 'pos_default_payment_method')) {
                $table->dropColumn('pos_default_payment_method');
            }
            
            // Adicionar nova coluna com foreign key para treasury_payment_methods
            if (!Schema::hasColumn('invoicing_settings', 'pos_default_payment_method_id')) {
                $table->foreignId('pos_default_payment_method_id')
                      ->nullable()
                      ->after('pos_require_customer')
                      ->constrained('treasury_payment_methods')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            // Remover foreign key e coluna nova
            if (Schema::hasColumn('invoicing_settings', 'pos_default_payment_method_id')) {
                $table->dropForeign(['pos_default_payment_method_id']);
                $table->dropColumn('pos_default_payment_method_id');
            }
            
            // Restaurar coluna antiga
            if (!Schema::hasColumn('invoicing_settings', 'pos_default_payment_method')) {
                $table->string('pos_default_payment_method', 50)
                      ->default('dinheiro')
                      ->after('pos_require_customer');
            }
        });
    }
};
