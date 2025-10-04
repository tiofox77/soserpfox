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
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->foreignId('default_tax_id')->nullable()->after('default_supplier_id')->constrained('invoicing_taxes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropForeign(['default_tax_id']);
            $table->dropColumn('default_tax_id');
        });
    }
};
