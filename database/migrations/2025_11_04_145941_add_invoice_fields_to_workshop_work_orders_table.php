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
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            // Adicionar campo invoice_id para vincular com fatura
            if (!Schema::hasColumn('workshop_work_orders', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('notes');
                $table->foreign('invoice_id')
                      ->references('id')
                      ->on('invoicing_sales_invoices')
                      ->onDelete('set null');
            }
            
            // Adicionar campo invoiced_at para registrar quando foi faturada
            if (!Schema::hasColumn('workshop_work_orders', 'invoiced_at')) {
                $table->timestamp('invoiced_at')->nullable()->after('invoice_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_work_orders', function (Blueprint $table) {
            // Remover foreign key e campos
            if (Schema::hasColumn('workshop_work_orders', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
            
            if (Schema::hasColumn('workshop_work_orders', 'invoiced_at')) {
                $table->dropColumn('invoiced_at');
            }
        });
    }
};
