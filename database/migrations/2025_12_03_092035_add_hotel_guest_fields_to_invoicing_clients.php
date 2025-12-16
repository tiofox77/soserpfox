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
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->boolean('hotel_vip')->default(false)->after('is_active');
            $table->boolean('hotel_blacklisted')->default(false)->after('hotel_vip');
            $table->string('document_type')->nullable()->after('nif');
            $table->string('document_number')->nullable()->after('document_type');
            $table->string('nationality')->nullable()->after('document_number');
            $table->date('birth_date')->nullable()->after('nationality');
            $table->string('gender')->nullable()->after('birth_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->dropColumn(['hotel_vip', 'hotel_blacklisted', 'document_type', 'document_number', 'nationality', 'birth_date', 'gender']);
        });
    }
};
