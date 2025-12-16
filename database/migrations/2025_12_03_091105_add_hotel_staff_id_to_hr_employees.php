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
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('hotel_staff_id')->nullable()->after('user_id');
            $table->foreign('hotel_staff_id')->references('id')->on('hotel_staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['hotel_staff_id']);
            $table->dropColumn('hotel_staff_id');
        });
    }
};
