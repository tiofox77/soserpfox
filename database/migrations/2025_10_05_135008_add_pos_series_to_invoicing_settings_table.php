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
            $table->string('pos_series', 10)->default('FR')->after('receipt_series');
            $table->integer('pos_next_number')->default(1)->after('pos_series');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn(['pos_series', 'pos_next_number']);
        });
    }
};
