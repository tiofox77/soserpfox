<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Torna os números de documento únicos POR TENANT (empresa) em vez de globais.
     *
     * Contexto: cada tenant é uma empresa/entidade legal independente. Conforme SAFT-AO,
     * a numeração de documentos (FR A 2026/000001, etc.) é sequencial e única DENTRO de
     * cada empresa. Duas empresas diferentes podem legitimamente ter o mesmo número.
     *
     * A unique global anterior (ex.: invoicing_sales_invoices_invoice_number_unique)
     * provocava "Duplicate entry" quando duas empresas geravam o mesmo número.
     */

    // tabela => coluna do número
    protected array $map = [
        'invoicing_sales_invoices'    => 'invoice_number',
        'invoicing_purchase_invoices' => 'invoice_number',
        'invoicing_sales_proformas'   => 'proforma_number',
        'invoicing_purchase_proformas'=> 'proforma_number',
        'invoicing_receipts'          => 'receipt_number',
        'invoicing_credit_notes'      => 'credit_note_number',
        'invoicing_debit_notes'       => 'debit_note_number',
    ];

    public function up(): void
    {
        $db = DB::getDatabaseName();

        foreach ($this->map as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            // 1) Remover unique GLOBAL de coluna única (qualquer índice unique só nesta coluna)
            $singleColIndexes = DB::select("
                SELECT s.INDEX_NAME, COUNT(*) AS col_count,
                       MAX(CASE WHEN s.COLUMN_NAME = ? THEN 1 ELSE 0 END) AS has_col
                FROM information_schema.STATISTICS s
                WHERE s.TABLE_SCHEMA = ? AND s.TABLE_NAME = ? AND s.NON_UNIQUE = 0
                  AND s.INDEX_NAME <> 'PRIMARY'
                GROUP BY s.INDEX_NAME
                HAVING col_count = 1 AND has_col = 1
            ", [$column, $db, $table]);

            foreach ($singleColIndexes as $idx) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$idx->INDEX_NAME}`");
            }

            // 2) Criar unique composto (tenant_id, coluna) se ainda não existir
            $compositeName = "{$table}_tenant_{$column}_unique";
            $exists = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
                LIMIT 1
            ", [$db, $table, $compositeName]);

            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$compositeName}` (`tenant_id`, `{$column}`)");
            }
        }
    }

    public function down(): void
    {
        $db = DB::getDatabaseName();

        foreach ($this->map as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            // Remover unique composto
            $compositeName = "{$table}_tenant_{$column}_unique";
            $exists = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
                LIMIT 1
            ", [$db, $table, $compositeName]);
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$compositeName}`");
            }

            // Recriar unique global de coluna única
            $globalName = "{$table}_{$column}_unique";
            $existsGlobal = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
                LIMIT 1
            ", [$db, $table, $globalName]);
            if (empty($existsGlobal)) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$globalName}` (`{$column}`)");
            }
        }
    }
};
