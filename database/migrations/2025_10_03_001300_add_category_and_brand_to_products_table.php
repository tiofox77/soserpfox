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
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('tenant_id')->constrained('invoicing_categories')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('invoicing_brands')->onDelete('set null');
            $table->foreignId('supplier_id')->nullable()->after('brand_id')->constrained('invoicing_suppliers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['category_id', 'brand_id', 'supplier_id']);
        });
    }
};
