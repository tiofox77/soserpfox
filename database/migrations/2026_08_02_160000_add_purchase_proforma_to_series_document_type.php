<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Acrescenta `purchase_proforma` ao ENUM de `invoicing_series.document_type`.
 *
 * A proforma de COMPRA não tinha valor próprio: só existia `proforma`, que é a
 * de venda. Duas séries com o mesmo document_type são indistinguíveis para o
 * `getIssuanceSeries()`, que escolheria uma pela ordem de is_default/id — ou
 * seja, uma proforma de compra podia sair numerada na série das vendas.
 *
 * Puramente aditivo: nenhuma linha existente muda de valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE invoicing_series MODIFY document_type
             ENUM('invoice','proforma','receipt','credit_note','debit_note',
                  'pos','purchase','purchase_proforma','advance','transport')
             NOT NULL"
        );
    }

    public function down(): void
    {
        // Só reverte se ninguém tiver usado o valor novo — caso contrário
        // apagaria séries em uso.
        $emUso = DB::table('invoicing_series')->where('document_type', 'purchase_proforma')->count();

        if ($emUso > 0) {
            throw new \RuntimeException(
                "Existem {$emUso} série(s) com document_type='purchase_proforma'. "
                . 'Reverter apagaria a sua classificação.'
            );
        }

        DB::statement(
            "ALTER TABLE invoicing_series MODIFY document_type
             ENUM('invoice','proforma','receipt','credit_note','debit_note',
                  'pos','purchase','advance','transport')
             NOT NULL"
        );
    }
};
