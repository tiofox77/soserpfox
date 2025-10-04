<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // alterar ENUM de status em sales_invoices
        DB::statement("ALTER TABLE `invoicing_sales_invoices` MODIFY COLUMN `status` ENUM('draft', 'pending', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled') DEFAULT 'draft'");
        
        // alterar ENUM de status em purchase_invoices
        DB::statement("ALTER TABLE `invoicing_purchase_invoices` MODIFY COLUMN `status` ENUM('draft', 'pending', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled') DEFAULT 'draft'");
        
        // Atualizar registros existentes com status 'partial' para 'partially_paid'
        DB::table('invoicing_sales_invoices')
            ->where('status', 'partial')
            ->update(['status' => 'partially_paid']);
            
        DB::table('invoicing_purchase_invoices')
            ->where('status', 'partial')
            ->update(['status' => 'partially_paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para o ENUM original
        DB::statement("ALTER TABLE `invoicing_sales_invoices` MODIFY COLUMN `status` ENUM('draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'draft'");
        
        DB::statement("ALTER TABLE `invoicing_purchase_invoices` MODIFY COLUMN `status` ENUM('draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'draft'");
    }
};
