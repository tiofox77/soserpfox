<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pagamento de uma venda POS (multi-tender).
 *
 * Uma venda pode ser paga em partes: metade em numerário, metade em
 * multicaixa. Cada parte é uma linha aqui. O campo payment_method da factura
 * fica como resumo ('multiple' ou o método único) para retro-compatibilidade
 * com talões e relatórios antigos. A tesouraria continua a receber uma linha
 * por parte — não precisou de mudar.
 *
 * Molde: workshop_work_order_payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_sale_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_invoice_id')->constrained('invoicing_sales_invoices')->cascadeOnDelete();
            $t->unsignedBigInteger('tenant_id');
            // Código do método (cash, card, bank_transfer, …) e o id do
            // TreasuryPaymentMethod quando resolvido.
            $t->string('payment_method', 50);
            $t->unsignedBigInteger('payment_method_id')->nullable();
            $t->decimal('amount', 15, 2);
            $t->string('reference', 100)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'sales_invoice_id']);
            $t->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_sale_payments');
    }
};
