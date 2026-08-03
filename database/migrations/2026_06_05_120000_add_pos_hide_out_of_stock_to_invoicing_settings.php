<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_settings', 'pos_hide_out_of_stock')) {
                $table->boolean('pos_hide_out_of_stock')
                    ->default(true)
                    ->after('pos_allow_negative_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_settings', 'pos_hide_out_of_stock')) {
                $table->dropColumn('pos_hide_out_of_stock');
            }
        });
    }
};
