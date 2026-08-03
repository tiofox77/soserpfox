<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona local_uuid a sales_invoices para idempotência das vendas POS offline.
 * Quando uma venda é criada offline no PWA, recebe um UUID local; ao sincronizar,
 * o servidor usa este UUID para evitar duplicados (retry / duplo envio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_sales_invoices', 'local_uuid')) {
                $table->string('local_uuid', 80)->nullable()->after('invoice_number');
                $table->index(['tenant_id', 'local_uuid']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_sales_invoices', 'local_uuid')) {
                $table->dropIndex(['tenant_id', 'local_uuid']);
                $table->dropColumn('local_uuid');
            }
        });
    }
};
