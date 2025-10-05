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
            $table->string('number_format', 20)->default('angola')->after('default_payment_method');
            $table->tinyInteger('decimal_places')->default(2)->after('number_format');
            $table->string('rounding_mode', 20)->default('normal')->after('decimal_places');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn(['number_format', 'decimal_places', 'rounding_mode']);
        });
    }
};
