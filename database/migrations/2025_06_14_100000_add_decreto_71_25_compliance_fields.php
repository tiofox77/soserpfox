<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decreto Presidencial n.º 71/25 — Compliance fields
 * 
 * Adds missing fields required by Angola's new electronic invoicing law:
 * - delivery_date/delivery_location on sales invoices
 * - reason_text (mandatory expression) on credit notes
 * - SAFT fields on credit/debit notes (series, hash, system_entry_date)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Sales Invoices — delivery info (Art. 6º RJF)
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_sales_invoices', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('due_date')
                    ->comment('Data de disponibilização dos bens ao adquirente (Art. 6º RJF)');
            }
            if (!Schema::hasColumn('invoicing_sales_invoices', 'delivery_location')) {
                $table->string('delivery_location')->nullable()->after('delivery_date')
                    ->comment('Local de disponibilização dos bens (Art. 6º RJF)');
            }
        });

        // 2. Credit Notes — mandatory expression + SAFT fields
        Schema::table('invoicing_credit_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_credit_notes', 'reason_text')) {
                $table->string('reason_text')->nullable()->after('reason')
                    ->comment('Expressão obrigatória: anulação ou rectificação (Art. 12º RJF)');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'series_id')) {
                $table->foreignId('series_id')->nullable()->after('tenant_id')
                    ->constrained('invoicing_series')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'system_entry_date')) {
                $table->datetime('system_entry_date')->nullable()->after('issue_date');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'invoice_status')) {
                $table->string('invoice_status', 1)->nullable()->after('status')
                    ->comment('SAFT status: F=Final, A=Anulado, R=Rascunho');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'hash')) {
                $table->text('hash')->nullable()->after('saft_hash');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'hash_previous')) {
                $table->text('hash_previous')->nullable()->after('hash');
            }
            if (!Schema::hasColumn('invoicing_credit_notes', 'hash_control')) {
                $table->string('hash_control', 5)->nullable()->after('hash_previous');
            }
        });

        // 3. Debit Notes — SAFT fields
        Schema::table('invoicing_debit_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_debit_notes', 'series_id')) {
                $table->foreignId('series_id')->nullable()->after('tenant_id')
                    ->constrained('invoicing_series')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'system_entry_date')) {
                $table->datetime('system_entry_date')->nullable()->after('issue_date');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'invoice_status')) {
                $table->string('invoice_status', 1)->nullable()->after('status')
                    ->comment('SAFT status: F=Final, A=Anulado, R=Rascunho');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'hash')) {
                $table->text('hash')->nullable()->after('saft_hash');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'hash_previous')) {
                $table->text('hash_previous')->nullable()->after('hash');
            }
            if (!Schema::hasColumn('invoicing_debit_notes', 'hash_control')) {
                $table->string('hash_control', 5)->nullable()->after('hash_previous');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'delivery_location']);
        });

        Schema::table('invoicing_credit_notes', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
            $table->dropColumn(['reason_text', 'series_id', 'system_entry_date', 'invoice_status', 'hash', 'hash_previous', 'hash_control']);
        });

        Schema::table('invoicing_debit_notes', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
            $table->dropColumn(['series_id', 'system_entry_date', 'invoice_status', 'hash', 'hash_previous', 'hash_control']);
        });
    }
};
