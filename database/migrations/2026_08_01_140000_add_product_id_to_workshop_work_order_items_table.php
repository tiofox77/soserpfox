<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `product_id` e `invoice_id` aos itens da Ordem de Serviço.
 *
 * O modelo WorkOrderItem já declarava as duas colunas em $fillable e todo o
 * módulo depende delas — WorkOrderManagement::addItem() envia sempre
 * 'product_id' e WorkOrder::processStockMovement() filtra por
 * whereNotNull('product_id') — mas NENHUMA migração as criava. Resultado: pôr
 * uma peça ou um serviço numa OS rebentava com "Unknown column 'product_id'",
 * e por isso a baixa de stock da oficina nunca chegou sequer a poder correr.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_work_order_items', function (Blueprint $table) {
            // Peça do catálogo. Nulo nos serviços e nas peças avulsas.
            $table->foreignId('product_id')
                ->nullable()
                ->after('service_id')
                ->constrained('invoicing_products')
                ->nullOnDelete();

            // Factura onde esta linha foi facturada (rastreio por linha).
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('product_id')
                ->constrained('invoicing_sales_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_work_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
