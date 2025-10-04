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
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('related_id')->nullable()->change();
            $table->string('related_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('related_id')->nullable(false)->change();
            $table->string('related_type')->nullable(false)->change();
        });
    }
};
