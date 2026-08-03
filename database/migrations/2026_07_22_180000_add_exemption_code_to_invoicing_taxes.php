<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * invoicing_taxes passa a guardar o CÓDIGO AGT de isenção (varchar 10), não só
 * a descrição. O regime é a fonte canónica: produtos e itens de documento
 * derivam o código daqui.
 *
 * Sem isto, o pré-preenchimento do formulário de Produtos copiava a DESCRIÇÃO
 * ("Regime Especial de Isenção (Artigo 53.º)", 40 chars) para
 * products.exemption_reason, e a venda falhava ao inserir esse texto em
 * invoicing_*_items.tax_exemption_code (varchar 10) — "Data too long".
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoicing_taxes', 'exemption_code')) {
            Schema::table('invoicing_taxes', function (Blueprint $table) {
                $table->string('exemption_code', 10)->nullable()->after('saft_type');
            });
        }

        // Backfill: resolver o código a partir da descrição já existente.
        $rows = DB::table('invoicing_taxes')
            ->whereNull('exemption_code')
            ->whereNotNull('exemption_reason')
            ->where('exemption_reason', '<>', '')
            ->get(['id', 'exemption_reason']);

        foreach ($rows as $row) {
            $code = \App\Models\Product::normalizeExemptionCode($row->exemption_reason);
            if ($code) {
                DB::table('invoicing_taxes')->where('id', $row->id)->update(['exemption_code' => $code]);
            }
        }

        // Regime de exclusão (Artigo 53.º) — garantir M04 mesmo sem match textual.
        DB::table('invoicing_taxes')
            ->where('code', 'ISENTO-EXCL')
            ->whereNull('exemption_code')
            ->update(['exemption_code' => 'M04']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoicing_taxes', 'exemption_code')) {
            Schema::table('invoicing_taxes', function (Blueprint $table) {
                $table->dropColumn('exemption_code');
            });
        }
    }
};
