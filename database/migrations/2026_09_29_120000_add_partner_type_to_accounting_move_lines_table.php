<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O TERCEIRO NA LINHA — a peça que faltava para a conta-corrente.
 *
 * A coluna `partner_id` já existia nas linhas desde o início, mas NUNCA era
 * preenchida (nem o IntegrationService nem o PostingService lhe tocavam) e não
 * dizia de que LADO era o terceiro: o mesmo id podia ser um cliente ou um
 * fornecedor, em tabelas diferentes (`invoicing_clients` vs
 * `invoicing_suppliers`). Sem saber o tipo, não se resolve o nome nem se separa
 * quem nos deve de quem devemos.
 *
 * `partner_type` = 'client' | 'supplier'. Fica indexado com o id para o extrato
 * e os saldos por terceiro saírem de um `group by` e não de uma varredura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_move_lines', function (Blueprint $table) {
            $table->string('partner_type', 20)->nullable()->after('partner_id');
            $table->index(['tenant_id', 'partner_type', 'partner_id'], 'aml_terceiro_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_move_lines', function (Blueprint $table) {
            $table->dropIndex('aml_terceiro_idx');
            $table->dropColumn('partner_type');
        });
    }
};
