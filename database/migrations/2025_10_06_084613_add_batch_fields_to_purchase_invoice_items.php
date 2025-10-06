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
        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('product_id')->comment('Número do lote');
            $table->date('manufacturing_date')->nullable()->after('batch_number')->comment('Data de fabricação');
            $table->date('expiry_date')->nullable()->after('manufacturing_date')->comment('Data de validade');
            $table->integer('alert_days')->default(30)->after('expiry_date')->comment('Dias de alerta antes da validade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'manufacturing_date', 'expiry_date', 'alert_days']);
        });
    }
};
