<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OS DOCUMENTOS DO ECRÃ «DOCUMENTOS» NO FECHO DE TURNO (26/09/2026).
 *
 * Quem tem um turno aberto no POS e emite uma factura, uma factura-recibo A4,
 * uma nota de crédito ou de débito ou um adiantamento pelo ecrã dos Documentos
 * passa a vê-los no fecho — por opção da empresa (`turno_inclui_documentos`,
 * ligada por omissão). Ver App\Services\POS\DocumentosNoTurno.
 *
 * O turno ganha um tipo de movimento: `a_prazo`. É o documento que aparece no
 * fecho sem mexer na gaveta — a factura por pagar, a nota de débito, a nota
 * de crédito de uma factura que ninguém pagou.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Alterar um ENUM em MySQL é reescrever a coluna: SQL cru, com os
        // tipos de sempre todos lá (ver 2026_09_10_090000).
        DB::statement("
            ALTER TABLE invoicing_pos_shift_transactions
            MODIFY COLUMN type ENUM('invoice','receipt','credit_note','a_prazo','adjustment','withdrawal','deposit') NOT NULL
        ");

        Schema::table('invoicing_settings', function (Blueprint $t) {
            if (! Schema::hasColumn('invoicing_settings', 'turno_inclui_documentos')) {
                $t->boolean('turno_inclui_documentos')->default(true)->after('pos_require_customer');
            }
        });
    }

    public function down(): void
    {
        // Os movimentos a prazo saem primeiro: encolher o ENUM com eles lá
        // deixava linhas sem tipo a contar para o turno.
        DB::table('invoicing_pos_shift_transactions')->where('type', 'a_prazo')->delete();

        DB::statement("
            ALTER TABLE invoicing_pos_shift_transactions
            MODIFY COLUMN type ENUM('invoice','receipt','credit_note','adjustment','withdrawal','deposit') NOT NULL
        ");

        Schema::table('invoicing_settings', function (Blueprint $t) {
            if (Schema::hasColumn('invoicing_settings', 'turno_inclui_documentos')) {
                $t->dropColumn('turno_inclui_documentos');
            }
        });
    }
};
