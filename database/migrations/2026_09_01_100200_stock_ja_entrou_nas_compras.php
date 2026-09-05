<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca a factura de compra cuja mercadoria JÁ ENTROU por recepção.
 *
 * O `PurchaseInvoiceObserver` dá entrada de stock quando a factura passa a
 * sent/pending/paid/partially_paid. No circuito novo das Compras, a mercadoria
 * entra na RECEPÇÃO da encomenda — antes de haver factura. Sem esta marca, ao
 * facturar uma encomenda já recebida o stock entrava duas vezes: uma na
 * recepção (real) e outra na factura (fantasma).
 *
 * Default `false` — para as facturas de compra que já existem nada muda: o
 * caminho de sempre continua a dar entrada como sempre deu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->boolean('stock_ja_entrou')->default(false)->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->dropColumn('stock_ja_entrou');
        });
    }
};
