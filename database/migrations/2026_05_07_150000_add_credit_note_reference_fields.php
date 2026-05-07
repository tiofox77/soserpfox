<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AGT v1.2 — Campos referenceInfo nas linhas de NC/ND.
 *
 * referenceInfo (object) é obrigatório para NC/ND e tem 3 campos:
 *  - reference (str)              → reference_invoice_no
 *  - referenceItemLineNo (int)    → reference_item_line_no
 *  - reason (str opc)             → reference_reason
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoicing_credit_note_items', 'invoicing_debit_note_items'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'reference_invoice_no')) {
                    $table->string('reference_invoice_no', 60)->nullable()
                        ->comment('AGT referenceInfo.reference — número da factura de origem');
                }
                if (!Schema::hasColumn($tbl, 'reference_item_line_no')) {
                    $table->unsignedInteger('reference_item_line_no')->nullable()
                        ->comment('AGT referenceInfo.referenceItemLineNo — linha da factura de origem');
                }
                if (!Schema::hasColumn($tbl, 'reference_reason')) {
                    $table->string('reference_reason', 60)->nullable()
                        ->comment('AGT referenceInfo.reason — motivo da NC/ND');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['invoicing_credit_note_items', 'invoicing_debit_note_items'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) {
                foreach (['reference_invoice_no', 'reference_item_line_no', 'reference_reason'] as $c) {
                    if (Schema::hasColumn($table->getTable(), $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
