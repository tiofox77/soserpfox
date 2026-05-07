<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos SAFT-AO obrigatórios para Notas de Crédito
        Schema::table('invoicing_credit_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_credit_notes', 'net_total')) {
                $table->decimal('net_total', 15, 2)->nullable()->after('subtotal');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'tax_payable')) {
                $table->decimal('tax_payable', 15, 2)->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'gross_total')) {
                $table->decimal('gross_total', 15, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'atcud')) {
                $table->string('atcud')->nullable()->after('credit_note_number');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'source_billing')) {
                $table->string('source_billing', 10)->nullable()->default('P')->after('invoice_status');
            }
        });

        // Campos SAFT-AO obrigatórios para Notas de Débito
        Schema::table('invoicing_debit_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_debit_notes', 'net_total')) {
                $table->decimal('net_total', 15, 2)->nullable()->after('subtotal');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'tax_payable')) {
                $table->decimal('tax_payable', 15, 2)->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'gross_total')) {
                $table->decimal('gross_total', 15, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'atcud')) {
                $table->string('atcud')->nullable()->after('debit_note_number');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'source_billing')) {
                $table->string('source_billing', 10)->nullable()->default('P')->after('invoice_status');
            }
        });

        // Campos SAFT-AO para Recibos
        Schema::table('invoicing_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_receipts', 'hash')) {
                $table->string('hash')->nullable()->after('saft_hash');
            }
            if (!Schema::hasColumn('invoicing_receipts', 'hash_previous')) {
                $table->string('hash_previous')->nullable()->after('hash');
            }
            if (!Schema::hasColumn('invoicing_receipts', 'hash_control')) {
                $table->string('hash_control', 10)->nullable()->after('hash_previous');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_credit_notes', function (Blueprint $table) {
            $table->dropColumn(['net_total', 'tax_payable', 'gross_total', 'atcud', 'source_billing']);
        });
        Schema::table('invoicing_debit_notes', function (Blueprint $table) {
            $table->dropColumn(['net_total', 'tax_payable', 'gross_total', 'atcud', 'source_billing']);
        });
        Schema::table('invoicing_receipts', function (Blueprint $table) {
            $table->dropColumn(['hash', 'hash_previous', 'hash_control']);
        });
    }
};
