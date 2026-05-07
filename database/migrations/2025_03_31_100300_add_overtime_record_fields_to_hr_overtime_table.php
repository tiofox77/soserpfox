<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_overtime', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_overtime', 'input_type')) {
                $table->enum('input_type', ['time_range', 'daily', 'monthly'])->default('monthly')->after('overtime_type');
            }
            if (!Schema::hasColumn('hr_overtime', 'direct_hours')) {
                $table->decimal('direct_hours', 8, 2)->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('hr_overtime', 'period_type')) {
                $table->string('period_type', 10)->default('month')->after('direct_hours');
            }
            if (!Schema::hasColumn('hr_overtime', 'is_night_shift')) {
                $table->boolean('is_night_shift')->default(false)->after('period_type');
            }
            if (!Schema::hasColumn('hr_overtime', 'rate')) {
                $table->decimal('rate', 15, 2)->nullable()->after('hourly_rate');
            }
            if (!Schema::hasColumn('hr_overtime', 'amount')) {
                $table->decimal('amount', 15, 2)->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('hr_overtime', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('payroll_id');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_overtime', function (Blueprint $table) {
            if (Schema::hasColumn('hr_overtime', 'created_by')) {
                $table->dropForeign(['created_by']);
            }
            $columns = ['input_type', 'direct_hours', 'period_type', 'is_night_shift', 'rate', 'amount', 'created_by'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('hr_overtime', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
