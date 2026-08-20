<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Um recibo de COMPRA precisa de sítio para dizer que factura pagou.
 *
 * A tabela `invoicing_receipts` serve os dois tipos (`type` = sale|purchase),
 * mas só tinha `invoice_id`, com chave estrangeira para
 * `invoicing_sales_invoices`. Num recibo de compra escrevia-se lá o id de uma
 * factura de COMPRA, e a base recusava a linha inteira:
 *
 *   SQLSTATE[23000] ... a foreign key constraint fails
 *   (`invoicing_receipts`, CONSTRAINT `invoicing_receipts_invoice_id_foreign`
 *    FOREIGN KEY (`invoice_id`) REFERENCES `invoicing_sales_invoices` (`id`))
 *
 * Resultado prático: pagar uma factura de compra NUNCA funcionou. Ninguém
 * deu por isso porque o erro ficava no log e o ecrã dizia só "Erro ao
 * registrar pagamento" — foi preciso a captura de erros para o ver.
 *
 * PORQUÊ UMA COLUNA NOVA E NÃO TIRAR A CHAVE ESTRANGEIRA
 * -----------------------------------------------------
 * Tirar a chave deixava `invoice_id` a apontar ora para uma tabela ora para
 * outra, sem nada a garantir qual. Duas colunas com chave própria dizem, só
 * de se olhar para o esquema, o que cada recibo paga — e apanham o erro na
 * base em vez de o deixar passar em silêncio.
 *
 * É o mesmo desenho que os movimentos de caixa já usavam ao lado
 * (`invoice_id` / `purchase_id` em PaymentModal::createTreasuryTransaction).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoicing_receipts')
            || Schema::hasColumn('invoicing_receipts', 'purchase_invoice_id')) {
            return;
        }

        Schema::table('invoicing_receipts', function (Blueprint $t) {
            $t->foreignId('purchase_invoice_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('invoicing_purchase_invoices')
                // Apagar a factura não pode levar o recibo com ela: o dinheiro
                // entrou e tem de continuar a aparecer na tesouraria.
                ->onDelete('set null');

            $t->index('purchase_invoice_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoicing_receipts')
            && Schema::hasColumn('invoicing_receipts', 'purchase_invoice_id')) {
            Schema::table('invoicing_receipts', function (Blueprint $t) {
                $t->dropConstrainedForeignId('purchase_invoice_id');
            });
        }
    }
};
