<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_items', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_payroll_items', 'night_shift_allowance')) {
                $table->decimal('night_shift_allowance', 15, 2)->default(0)->after('night_shift_pay');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'night_shift_days')) {
                $table->integer('night_shift_days')->default(0)->after('night_shift_allowance');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'family_allowance')) {
                $table->decimal('family_allowance', 15, 2)->default(0)->after('night_shift_days');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'position_subsidy')) {
                $table->decimal('position_subsidy', 15, 2)->default(0)->after('family_allowance');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'performance_subsidy')) {
                $table->decimal('performance_subsidy', 15, 2)->default(0)->after('position_subsidy');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'christmas_subsidy_amount')) {
                $table->decimal('christmas_subsidy_amount', 15, 2)->default(0)->after('subsidy_14th');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'vacation_subsidy_amount')) {
                $table->decimal('vacation_subsidy_amount', 15, 2)->default(0)->after('christmas_subsidy_amount');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'discount_deduction')) {
                $table->decimal('discount_deduction', 15, 2)->default(0)->after('loan_deduction');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'present_days')) {
                $table->integer('present_days')->default(0)->after('worked_days');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'late_days')) {
                $table->integer('late_days')->default(0)->after('absence_days');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'total_working_days')) {
                $table->integer('total_working_days')->default(0)->after('late_days');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'overtime_amount')) {
                $table->decimal('overtime_amount', 15, 2)->default(0)->after('overtime_hours');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'food_deduction')) {
                $table->decimal('food_deduction', 15, 2)->default(0)->after('other_deductions');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'has_christmas_subsidy')) {
                $table->boolean('has_christmas_subsidy')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'has_vacation_subsidy')) {
                $table->boolean('has_vacation_subsidy')->default(false)->after('has_christmas_subsidy');
            }
            if (!Schema::hasColumn('hr_payroll_items', 'additional_bonus')) {
                $table->decimal('additional_bonus', 15, 2)->default(0)->after('has_vacation_subsidy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_items', function (Blueprint $table) {
            $columns = [
                'night_shift_allowance', 'night_shift_days', 'family_allowance',
                'position_subsidy', 'performance_subsidy', 'christmas_subsidy_amount',
                'vacation_subsidy_amount', 'discount_deduction', 'present_days',
                'late_days', 'total_working_days', 'overtime_amount', 'food_deduction',
                'has_christmas_subsidy', 'has_vacation_subsidy', 'additional_bonus',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('hr_payroll_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
