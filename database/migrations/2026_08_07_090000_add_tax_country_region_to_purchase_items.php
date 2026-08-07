<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Região fiscal nas linhas dos documentos de COMPRA.
 *
 * As linhas de venda já a têm — invoicing_sales_invoice_items e
 * invoicing_sales_proforma_items. As de compra não, e por isso o selector de
 * região não podia sequer ser levado a esses ecrãs: não havia onde gravar.
 *
 * Cabinda tem regime de IVA próprio (AO-CAB) e o que o determina é o local da
 * operação. Uma compra feita em Cabinda tem o mesmo direito ao regime que uma
 * venda, e hoje era registada como continental sem alternativa.
 *
 * Nulo = por decidir, e a leitura recai em 'AO', que é o que já acontecia de
 * facto. Nenhum documento existente muda de valor com esta migração.
 */
return new class extends Migration
{
    private const TABELAS = [
        'invoicing_purchase_invoice_items',
        'invoicing_purchase_proforma_items',
    ];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            if (!Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'tax_country_region')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) {
                $table->string('tax_country_region', 10)->nullable()->after('tax_rate');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            if (!Schema::hasTable($tabela) || !Schema::hasColumn($tabela, 'tax_country_region')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('tax_country_region');
            });
        }
    }
};
