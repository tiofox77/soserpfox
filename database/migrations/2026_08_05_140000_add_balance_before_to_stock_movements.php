<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo ANTES do movimento.
 *
 * O histórico mostrava a quantidade e mais nada: a coluna "Saldo" vinha vazia
 * em todas as linhas porque só o ecrã de ajustes preenchia balance_after —
 * vendas, transferências e entradas deixavam-no nulo.
 *
 * Sem o saldo anterior não se lê um ajuste: a quantidade de um ajuste é o valor
 * FINAL, não uma variação, por isso "= 18" não diz se subiu ou desceu, nem
 * quanto. Com os dois saldos lê-se "de 12 para 18, ajustada +6".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_stock_movements', 'balance_before')) {
                $table->decimal('balance_before', 18, 4)->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_stock_movements', 'balance_before')) {
                $table->dropColumn('balance_before');
            }
        });
    }
};
