<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * events_reports não tinha tenant_id (isolava só por event_id), por isso o
 * report_number ficou com UNIQUE global. Fecha o último gap de numeração por-tenant:
 *   1) adiciona tenant_id (nullable + FK)
 *   2) backfill a partir do evento pai (events_events.tenant_id)
 *   3) troca o unique global de report_number por composto (tenant_id, report_number)
 *
 * Defensivo/idempotente (descobre o nome real do índice via information_schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_reports')) {
            return;
        }

        // 1) Adicionar tenant_id (se ainda não existe)
        if (!Schema::hasColumn('events_reports', 'tenant_id')) {
            Schema::table('events_reports', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->nullOnDelete();
            });
        }

        // 2) Backfill a partir do evento pai
        if (Schema::hasTable('events_events')) {
            DB::statement("
                UPDATE events_reports r
                JOIN events_events e ON e.id = r.event_id
                SET r.tenant_id = e.tenant_id
                WHERE r.tenant_id IS NULL
            ");
        }

        // 3) Global unique -> composto (tenant_id, report_number)
        $db = DB::getDatabaseName();
        $column = 'report_number';
        $table = 'events_reports';

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

        $compositeName = "{$table}_tenant_{$column}_unique";
        $exists = DB::select("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
        ", [$db, $table, $compositeName]);

        if (empty($exists)) {
            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$compositeName}` (`tenant_id`, `{$column}`)");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('events_reports')) {
            return;
        }

        $db = DB::getDatabaseName();
        $table = 'events_reports';
        $column = 'report_number';

        $compositeName = "{$table}_tenant_{$column}_unique";
        $exists = DB::select("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
        ", [$db, $table, $compositeName]);
        if (!empty($exists)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$compositeName}`");
        }
        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$table}_{$column}_unique` (`{$column}`)");

        if (Schema::hasColumn('events_reports', 'tenant_id')) {
            Schema::table('events_reports', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
