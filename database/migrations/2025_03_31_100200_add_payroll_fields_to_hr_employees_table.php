<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_employees', 'base_salary')) {
                $table->decimal('base_salary', 15, 2)->nullable()->after('salary');
            }
            if (!Schema::hasColumn('hr_employees', 'food_benefit')) {
                $table->decimal('food_benefit', 15, 2)->nullable()->after('meal_allowance');
            }
            if (!Schema::hasColumn('hr_employees', 'transport_benefit')) {
                $table->decimal('transport_benefit', 15, 2)->nullable()->after('transport_allowance');
            }
            if (!Schema::hasColumn('hr_employees', 'family_allowance')) {
                $table->decimal('family_allowance', 15, 2)->nullable()->after('transport_benefit');
            }
            if (!Schema::hasColumn('hr_employees', 'position_subsidy')) {
                $table->decimal('position_subsidy', 15, 2)->nullable()->after('family_allowance');
            }
            if (!Schema::hasColumn('hr_employees', 'performance_subsidy')) {
                $table->decimal('performance_subsidy', 15, 2)->nullable()->after('position_subsidy');
            }
            if (!Schema::hasColumn('hr_employees', 'employment_status')) {
                $table->enum('employment_status', ['active', 'on_leave', 'terminated', 'suspended', 'retired'])->default('active')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $columns = ['base_salary', 'food_benefit', 'transport_benefit', 'family_allowance', 'position_subsidy', 'performance_subsidy', 'employment_status'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('hr_employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
