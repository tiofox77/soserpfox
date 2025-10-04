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
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('tenant_id')->constrained('invoicing_series')->nullOnDelete();
            $table->text('saft_hash')->nullable()->after('notes')->comment('Hash SAFT-AO assinado (RSA-2048 Base64)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
            $table->dropColumn(['series_id', 'saft_hash']);
        });
    }
};
