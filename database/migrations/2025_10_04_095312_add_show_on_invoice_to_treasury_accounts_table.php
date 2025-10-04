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
        Schema::table('treasury_accounts', function (Blueprint $table) {
            $table->boolean('show_on_invoice')->default(false)->after('is_default');
            $table->integer('invoice_display_order')->nullable()->after('show_on_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_accounts', function (Blueprint $table) {
            $table->dropColumn(['show_on_invoice', 'invoice_display_order']);
        });
    }
};
