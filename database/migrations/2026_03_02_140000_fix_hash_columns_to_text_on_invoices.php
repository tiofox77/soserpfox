<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix hash_previous from VARCHAR(255) to TEXT on sales invoices
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->text('hash_previous')->nullable()->change();
        });

        // Fix hash_previous from VARCHAR(255) to TEXT on purchase invoices
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->text('hash_previous')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->string('hash_previous')->nullable()->change();
        });

        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->string('hash_previous')->nullable()->change();
        });
    }
};
