<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AS FACTURAS DE COMPRA NO FECHO DE TURNO (26/09/2026).
 *
 * Com a opção «Documentos no fecho de turno» ligada, a factura de compra que o
 * operador emite com o turno aberto também sai no fecho. É informativa: o
 * dinheiro de uma compra só sai da gaveta quando se regista o pagamento, e
 * esse já vai ao turno como saída (App\Services\POS\GavetaDoTurno). Por isso
 * precisa de um tipo seu, fora das vendas e do esperado.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE invoicing_pos_shift_transactions
            MODIFY COLUMN type ENUM('invoice','receipt','credit_note','a_prazo','compra','adjustment','withdrawal','deposit') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::table('invoicing_pos_shift_transactions')->where('type', 'compra')->delete();

        DB::statement("
            ALTER TABLE invoicing_pos_shift_transactions
            MODIFY COLUMN type ENUM('invoice','receipt','credit_note','a_prazo','adjustment','withdrawal','deposit') NOT NULL
        ");
    }
};
