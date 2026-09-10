<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A NOTA DE CRÉDITO PASSA A CABER NO TURNO.
 *
 * O turno do POS é a conta da gaveta, e nunca soube o que era uma devolução: a
 * tabela dos movimentos tem um ENUM de cinco tipos e a nota de crédito não é
 * nenhum deles. Resultado — 1177 movimentos gravados, todos `invoice`, e nem
 * um único a dizer que saiu dinheiro.
 *
 * Isso não é só um relatório incompleto: o «Dinheiro Esperado» é
 * `saldo inicial + vendas em dinheiro`, e uma devolução paga da gaveta fazia
 * faltar dinheiro ao fecho — uma diferença que o operador tinha de explicar e
 * que não era dele.
 *
 * As duas colunas novas guardam o que se devolveu, para o ecrã poder dizer
 * BRUTO, DEVOLVIDO e LÍQUIDO — o mesmo vocabulário que o relatório de vendas
 * do POS já usa. Sem elas, o líquido e o bruto seriam o mesmo número e não
 * havia como distinguir um turno de 100.000 sem devoluções de um de 150.000
 * com 50.000 devolvidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * O ENUM cresce; os cinco de sempre ficam onde estavam. Alterar um
         * ENUM em MySQL é reescrever a coluna — por isso vai em SQL cru, que é
         * a única forma de o fazer sem perder os valores.
         */
        DB::statement("
            ALTER TABLE invoicing_pos_shift_transactions
            MODIFY COLUMN type ENUM('invoice','receipt','credit_note','adjustment','withdrawal','deposit') NOT NULL
        ");

        Schema::table('invoicing_pos_shifts', function (Blueprint $t) {
            if (! Schema::hasColumn('invoicing_pos_shifts', 'total_credit_notes')) {
                $t->unsignedInteger('total_credit_notes')->default(0)->after('total_receipts');
            }

            if (! Schema::hasColumn('invoicing_pos_shifts', 'credit_notes_amount')) {
                // Guardado em POSITIVO: é «quanto se devolveu», não um saldo.
                // O sinal vive no movimento, que é onde ele quer dizer alguma
                // coisa (dinheiro a sair da gaveta).
                $t->decimal('credit_notes_amount', 15, 2)->default(0)->after('total_sales');
            }
        });
    }

    public function down(): void
    {
        /*
         * A VOLTA ATRÁS APAGA OS MOVIMENTOS DE DEVOLUÇÃO PRIMEIRO.
         *
         * Sem isso, encolher o ENUM deixaria linhas com um valor que a coluna
         * já não aceita — o MySQL trunca-as para vazio em silêncio e ficavam
         * movimentos sem tipo nenhum, a contar para o total sem se saber o que
         * são.
         */
        DB::table('invoicing_pos_shift_transactions')->where('type', 'credit_note')->delete();

        DB::statement("
            ALTER TABLE invoicing_pos_shift_transactions
            MODIFY COLUMN type ENUM('invoice','receipt','adjustment','withdrawal','deposit') NOT NULL
        ");

        Schema::table('invoicing_pos_shifts', function (Blueprint $t) {
            $t->dropColumn(['total_credit_notes', 'credit_notes_amount']);
        });
    }
};
