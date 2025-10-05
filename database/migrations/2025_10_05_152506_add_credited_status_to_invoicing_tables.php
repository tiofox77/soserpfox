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
        // Adicionar status 'credited' ao ENUM de sales_invoices
        DB::statement("ALTER TABLE `invoicing_sales_invoices` MODIFY COLUMN `status` ENUM('draft', 'pending', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled', 'credited') DEFAULT 'draft'");
        
        // Adicionar status 'credited' ao ENUM de purchase_invoices
        DB::statement("ALTER TABLE `invoicing_purchase_invoices` MODIFY COLUMN `status` ENUM('draft', 'pending', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled', 'credited') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para o ENUM sem 'credited'
        DB::statement("ALTER TABLE `invoicing_sales_invoices` MODIFY COLUMN `status` ENUM('draft', 'pending', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled') DEFAULT 'draft'");
        
        DB::statement("ALTER TABLE `invoicing_purchase_invoices` MODIFY COLUMN `status` ENUM('draft', 'pending', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled') DEFAULT 'draft'");
    }
};
