<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_settings', 'use_kitchen_workflow')) {
                $table->boolean('use_kitchen_workflow')->default(true)->after('require_open_shift');
            }
            if (!Schema::hasColumn('restaurant_settings', 'require_recipe_for_products')) {
                $table->boolean('require_recipe_for_products')->default(false)->after('use_kitchen_workflow');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['use_kitchen_workflow', 'require_recipe_for_products'],
                fn (string $column) => Schema::hasColumn('restaurant_settings', $column)
            ));
            if ($columns) $table->dropColumn($columns);
        });
    }
};
