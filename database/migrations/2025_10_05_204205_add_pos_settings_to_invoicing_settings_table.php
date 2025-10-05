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
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->boolean('pos_auto_print')->default(true)->after('default_terms');
            $table->boolean('pos_play_sounds')->default(true)->after('pos_auto_print');
            $table->boolean('pos_validate_stock')->default(true)->after('pos_play_sounds');
            $table->boolean('pos_allow_negative_stock')->default(false)->after('pos_validate_stock');
            $table->boolean('pos_show_product_images')->default(true)->after('pos_allow_negative_stock');
            $table->tinyInteger('pos_products_per_page')->default(12)->after('pos_show_product_images');
            $table->boolean('pos_auto_complete_sale')->default(false)->after('pos_products_per_page');
            $table->boolean('pos_require_customer')->default(false)->after('pos_auto_complete_sale');
            $table->foreignId('pos_default_payment_method_id')->nullable()->after('pos_require_customer')
                  ->constrained('treasury_payment_methods')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropForeign(['pos_default_payment_method_id']);
            $table->dropColumn([
                'pos_auto_print',
                'pos_play_sounds',
                'pos_validate_stock',
                'pos_allow_negative_stock',
                'pos_show_product_images',
                'pos_products_per_page',
                'pos_auto_complete_sale',
                'pos_require_customer',
                'pos_default_payment_method_id',
            ]);
        });
    }
};
