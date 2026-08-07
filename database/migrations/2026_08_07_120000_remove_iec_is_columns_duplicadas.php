<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove as colunas de IEC/IS acrescentadas por engano na migração anterior.
 *
 * O ERRO
 * ------
 * Auditei as tabelas de linhas à procura de colunas `iec_*` e `is_*`, não as
 * encontrei, e concluí que estes impostos não eram gravados em lado nenhum.
 * Estava errado: existe `invoicing_line_taxes` — uma tabela de impostos POR
 * LINHA, polimórfica, com exactamente a forma que a AGT usa (tax_type,
 * tax_percentage, tax_amount, pautal_code, verba_no, ...). E está em uso:
 * facturas de venda, notas de crédito e notas de débito já lá escrevem.
 *
 * Ou seja, o desenho certo — o mesmo que eu tinha descrito como o caminho a
 * seguir — já existia. As colunas que criei duplicavam-no.
 *
 * PORQUE SE REMOVEM
 * -----------------
 * Nunca chegaram a ter dados (verificado antes de largar). Deixá-las lá seria
 * pior do que inútil: passariam a ser um segundo sítio onde o mesmo facto pode
 * ficar guardado, e mais cedo ou mais tarde alguém escrevia num e lia do
 * outro. É a divergência que este sistema já pagou caro noutros sítios.
 *
 * O QUE FICA
 * ----------
 * Os códigos SAFT (tax_code, tax_exemption_code, tax_exemption_reason) FICAM
 * nas linhas de compra: essas colunas não são duplicação — as linhas de venda
 * têm-nas desde sempre e as de compra não tinham. São do IVA da própria linha,
 * não da lista de impostos extra.
 */
return new class extends Migration
{
    private const LINHAS = [
        'invoicing_sales_invoice_items',
        'invoicing_sales_proforma_items',
        'invoicing_purchase_invoice_items',
        'invoicing_purchase_proforma_items',
    ];

    private const DUPLICADAS = [
        'iec_pautal_code', 'iec_percentage', 'iec_amount',
        'is_verba_no', 'is_percentage', 'is_amount',
    ];

    public function up(): void
    {
        foreach (self::LINHAS as $tabela) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                foreach (self::DUPLICADAS as $coluna) {
                    if (Schema::hasColumn($tabela, $coluna)) {
                        $table->dropColumn($coluna);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Sem volta atrás de propósito: recriar estas colunas seria repor a
        // duplicação. O sítio dos impostos por linha é invoicing_line_taxes.
    }
};
