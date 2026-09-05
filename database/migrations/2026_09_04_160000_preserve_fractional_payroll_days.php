<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hr_payroll_items', function (Blueprint $table) {
            $table->decimal('worked_days', 6, 2)->default(0)->change();
            $table->decimal('present_days', 6, 2)->default(0)->change();
            $table->decimal('absence_days', 6, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Keep fractional values: reverting to integers would lose attendance data.
    }
};
