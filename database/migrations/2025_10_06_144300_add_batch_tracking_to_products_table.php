<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->boolean('track_batches')->default(false)->after('manage_stock')->comment('Rastrear por lotes');
            $table->boolean('track_expiry')->default(false)->after('track_batches')->comment('Controlar validade');
            $table->boolean('require_batch_on_purchase')->default(false)->after('track_expiry')->comment('Exigir lote na compra');
            $table->boolean('require_batch_on_sale')->default(false)->after('require_batch_on_purchase')->comment('Exigir lote na venda');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropColumn(['track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale']);
        });
    }
};
