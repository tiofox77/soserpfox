<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa a correção da numeração de documentos POR TENANT.
 *
 * A migração 2026_07_06_114500 tratou os 7 documentos principais (faturas, proformas,
 * recibos, notas de crédito/débito). Estas tabelas ficaram de fora mas têm o MESMO bug:
 * número gerado por-tenant + índice UNIQUE global -> colisão "Duplicate entry" quando
 * duas empresas geram o mesmo número.
 *
 *   invoicing_advances.advance_number         (AD/{ano}/NNNN — gerado por tenant)
 *   invoicing_purchase_orders.order_number    (por tenant)
 *   treasury_transactions.transaction_number  (TRX-{ano}-NNNN — por tenant)
 *   treasury_transfers.transfer_number        (TRF-{ano}-NNNN — por tenant)
 *   treasury_reconciliations.reconciliation_number (REC-{ano}-NNNN — por tenant)
 *   hotel_maintenance_orders.order_number     (por tenant)
 *   imports.import_number                     (IMP/{ano}/NNNN — por tenant)
 *   support_tickets.ticket_number             (composto é seguro; separa por empresa)
 *
 * Mesmo padrão defensivo: descobre o nome REAL do índice único de coluna única via
 * information_schema, remove-o, e cria o composto (tenant_id, coluna). Idempotente.
 */
return new class extends Migration
{
    protected array $map = [
        'invoicing_advances'        => 'advance_number',
        'invoicing_purchase_orders' => 'order_number',
        'treasury_transactions'     => 'transaction_number',
        'treasury_transfers'        => 'transfer_number',
        'treasury_reconciliations'  => 'reconciliation_number',
        'hotel_maintenance_orders'  => 'order_number',
        'imports'                   => 'import_number',
        'support_tickets'           => 'ticket_number',
    ];

    public function up(): void
    {
        $db = DB::getDatabaseName();

        foreach ($this->map as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)
                || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            // 1) Remover qualquer índice UNIQUE de coluna única sobre $column (nome real).
            $singleColIndexes = DB::select("
                SELECT s.INDEX_NAME
                FROM information_schema.STATISTICS s
                WHERE s.TABLE_SCHEMA = ? AND s.TABLE_NAME = ? AND s.NON_UNIQUE = 0
                  AND s.INDEX_NAME <> 'PRIMARY'
                GROUP BY s.INDEX_NAME
                HAVING COUNT(*) = 1
                   AND MAX(CASE WHEN s.COLUMN_NAME = ? THEN 1 ELSE 0 END) = 1
            ", [$db, $table, $column]);

            foreach ($singleColIndexes as $idx) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$idx->INDEX_NAME}`");
            }

            // 2) Criar composto (tenant_id, coluna) se ainda não existir.
            $compositeName = "{$table}_tenant_{$column}_unique";
            $exists = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
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

            $compositeName = "{$table}_tenant_{$column}_unique";
            $exists = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
            ", [$db, $table, $compositeName]);
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$compositeName}`");
            }

            $globalName = "{$table}_{$column}_unique";
            $existsGlobal = DB::select("
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
            ", [$db, $table, $globalName]);
            if (empty($existsGlobal)) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$globalName}` (`{$column}`)");
            }
        }
    }
};
