<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoicing_sales_invoices', 'payment_method')) {
            Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
                $table->string('payment_method', 50)
                    ->nullable()
                    ->after('notes')
                    ->comment('Método de pagamento (cash, transfer, multicaixa, etc) — usado por POS e Fatura-Recibo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoicing_sales_invoices', 'payment_method')) {
            Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};
