<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_attendances', 'time_in')) {
                $table->datetime('time_in')->nullable()->after('check_in');
            }
            if (!Schema::hasColumn('hr_attendances', 'time_out')) {
                $table->datetime('time_out')->nullable()->after('check_out');
            }
            if (!Schema::hasColumn('hr_attendances', 'hourly_rate')) {
                $table->decimal('hourly_rate', 15, 2)->nullable()->after('overtime_hours');
            }
            if (!Schema::hasColumn('hr_attendances', 'affects_payroll')) {
                $table->boolean('affects_payroll')->default(true)->after('hourly_rate');
            }
            if (!Schema::hasColumn('hr_attendances', 'remarks')) {
                $table->text('remarks')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $columns = ['time_in', 'time_out', 'hourly_rate', 'affects_payroll', 'remarks'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('hr_attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
