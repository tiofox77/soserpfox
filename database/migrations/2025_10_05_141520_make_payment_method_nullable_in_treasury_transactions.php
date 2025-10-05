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
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->change();
            $table->foreignId('cash_register_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable(false)->change();
            $table->foreignId('cash_register_id')->nullable(false)->change();
        });
    }
};
