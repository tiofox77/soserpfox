<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IEC e IS por linha, e os códigos SAFT que faltavam nas compras.
 *
 * O QUE FALTAVA
 * -------------
 * O Imposto Especial de Consumo e o Imposto de Selo são calculados e mostrados
 * nos totais das facturas de venda, e entram no total do documento — sem eles
 * netTotal + taxPayable ≠ grossTotal e a AGT recusa. Mas não eram GRAVADOS em
 * lado nenhum: nem nas facturas de venda. Reabrir um documento perdia-os, e
 * nada permitia reconstruir o que foi liquidado.
 *
 * E as linhas de compra não tinham sequer tax_code nem os campos de isenção,
 * que as de venda têm desde sempre.
 *
 * A FORMA
 * -------
 * A AGT trata os impostos de uma linha como uma LISTA (taxes[]): a mesma linha
 * pode ter IVA, IEC e IS ao mesmo tempo. O ecrã permite hoje um IEC e uma verba
 * de IS por linha, e é isso que estas colunas guardam — uma de cada.
 *
 * Se um dia for preciso mais do que um de cada tipo, o caminho é uma tabela de
 * impostos por linha, não mais colunas. Fica dito, porque a tentação seguinte
 * seria acrescentar iec_2_*.
 *
 * Nulo = não se aplica. Nenhum documento existente muda de valor.
 */
return new class extends Migration
{
    private const LINHAS = [
        'invoicing_sales_invoice_items',
        'invoicing_sales_proforma_items',
        'invoicing_purchase_invoice_items',
        'invoicing_purchase_proforma_items',
    ];

    /** Códigos SAFT que só as linhas de venda tinham. */
    private const SAFT = [
        'tax_code'             => 10,
        'tax_exemption_code'   => 20,
        'tax_exemption_reason' => 255,
    ];

    public function up(): void
    {
        foreach (self::LINHAS as $tabela) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                // IEC — Imposto Especial de Consumo. O código pautal identifica
                // a mercadoria na tabela da AGT.
                if (!Schema::hasColumn($tabela, 'iec_pautal_code')) {
                    $table->string('iec_pautal_code', 30)->nullable();
                }
                if (!Schema::hasColumn($tabela, 'iec_percentage')) {
                    $table->decimal('iec_percentage', 8, 4)->nullable();
                }
                if (!Schema::hasColumn($tabela, 'iec_amount')) {
                    $table->decimal('iec_amount', 15, 2)->nullable();
                }

                // IS — Imposto de Selo. A verba pode ser percentual ou de valor
                // fixo; por isso a percentagem é nula nas de valor fixo e o
                // montante é sempre o que conta.
                if (!Schema::hasColumn($tabela, 'is_verba_no')) {
                    $table->string('is_verba_no', 10)->nullable();
                }
                if (!Schema::hasColumn($tabela, 'is_percentage')) {
                    $table->decimal('is_percentage', 8, 4)->nullable();
                }
                if (!Schema::hasColumn($tabela, 'is_amount')) {
                    $table->decimal('is_amount', 15, 2)->nullable();
                }

                foreach (self::SAFT as $coluna => $tamanho) {
                    if (!Schema::hasColumn($tabela, $coluna)) {
                        $table->string($coluna, $tamanho)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $colunas = array_merge(
            ['iec_pautal_code', 'iec_percentage', 'iec_amount', 'is_verba_no', 'is_percentage', 'is_amount'],
            array_keys(self::SAFT)
        );

        foreach (self::LINHAS as $tabela) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela, $colunas) {
                foreach ($colunas as $coluna) {
                    if (Schema::hasColumn($tabela, $coluna)) {
                        $table->dropColumn($coluna);
                    }
                }
            });
        }
    }
};
