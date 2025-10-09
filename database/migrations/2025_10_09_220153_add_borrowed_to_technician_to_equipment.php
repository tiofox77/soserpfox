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
        if (Schema::hasTable('events_equipments_manager') && Schema::hasTable('events_technicians')) {
            Schema::table('events_equipments_manager', function (Blueprint $table) {
                if (!Schema::hasColumn('events_equipments_manager', 'borrowed_to_technician_id')) {
                    $table->unsignedBigInteger('borrowed_to_technician_id')->nullable()->after('borrowed_to_client_id');
                    $table->foreign('borrowed_to_technician_id')->references('id')->on('events_technicians')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('events_equipments_manager')) {
            Schema::table('events_equipments_manager', function (Blueprint $table) {
                if (Schema::hasColumn('events_equipments_manager', 'borrowed_to_technician_id')) {
                    $table->dropForeign(['borrowed_to_technician_id']);
                    $table->dropColumn('borrowed_to_technician_id');
                }
            });
        }
    }
};
