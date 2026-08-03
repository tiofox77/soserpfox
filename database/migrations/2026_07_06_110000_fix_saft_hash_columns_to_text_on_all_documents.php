<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converte saft_hash/hash/hash_previous para TEXT em todos os documentos de venda.
     * O hash SAFT-AO assinado (RSA-2048 em Base64) tem ~344 caracteres, ultrapassando
     * o limite VARCHAR(255) original, causando erro "Data too long for column".
     */
    public function up(): void
    {
        // Notas de Crédito — saft_hash era VARCHAR(255)
        if (Schema::hasColumn('invoicing_credit_notes', 'saft_hash')) {
            Schema::table('invoicing_credit_notes', function (Blueprint $table) {
                $table->text('saft_hash')->nullable()->change();
            });
        }

        // Notas de Débito — saft_hash era VARCHAR(255)
        if (Schema::hasColumn('invoicing_debit_notes', 'saft_hash')) {
            Schema::table('invoicing_debit_notes', function (Blueprint $table) {
                $table->text('saft_hash')->nullable()->change();
            });
        }

        // Recibos — saft_hash, hash e hash_previous eram VARCHAR(255)
        Schema::table('invoicing_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_receipts', 'saft_hash')) {
                $table->text('saft_hash')->nullable()->change();
            }
            if (Schema::hasColumn('invoicing_receipts', 'hash')) {
                $table->text('hash')->nullable()->change();
            }
            if (Schema::hasColumn('invoicing_receipts', 'hash_previous')) {
                $table->text('hash_previous')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoicing_credit_notes', 'saft_hash')) {
            Schema::table('invoicing_credit_notes', function (Blueprint $table) {
                $table->string('saft_hash')->nullable()->change();
            });
        }

        if (Schema::hasColumn('invoicing_debit_notes', 'saft_hash')) {
            Schema::table('invoicing_debit_notes', function (Blueprint $table) {
                $table->string('saft_hash')->nullable()->change();
            });
        }

        Schema::table('invoicing_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('invoicing_receipts', 'saft_hash')) {
                $table->string('saft_hash')->nullable()->change();
            }
            if (Schema::hasColumn('invoicing_receipts', 'hash')) {
                $table->string('hash')->nullable()->change();
            }
            if (Schema::hasColumn('invoicing_receipts', 'hash_previous')) {
                $table->string('hash_previous')->nullable()->change();
            }
        });
    }
};
