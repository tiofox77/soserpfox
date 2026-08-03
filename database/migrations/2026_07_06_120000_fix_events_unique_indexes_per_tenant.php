<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torna os identificadores do módulo Eventos únicos POR TENANT (não globais).
 *
 *   - events_events.event_number (EVT{ano}/NNNN)
 *   - events_teams.code          (ex.: EQ-001)
 *
 * Robusto: descobre o nome REAL do índice único de coluna única (que pode diferir
 * da convenção do Laravel em produção) e remove-o antes de criar o composto.
 *
 * NOTA: events_reports.report_number continua único global — a tabela não tem
 * coluna tenant_id (isolada por event_id); corrigir exigiria adicionar tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->makeCompositeUnique('events_events', 'event_number', 'events_events_tenant_number_unique');
        $this->makeCompositeUnique('events_teams', 'code', 'events_teams_tenant_code_unique');
    }

    public function down(): void
    {
        $this->dropCompositeRestoreGlobal('events_events', 'event_number', 'events_events_tenant_number_unique');
        $this->dropCompositeRestoreGlobal('events_teams', 'code', 'events_teams_tenant_code_unique');
    }

    private function makeCompositeUnique(string $table, string $col, string $newName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $indexes = collect(Schema::getIndexes($table));

        // Já existe o composto? nada a fazer (idempotente).
        if ($indexes->contains(fn ($i) => $i['name'] === $newName)) {
            return;
        }

        // Remover qualquer índice único de coluna ÚNICA sobre $col (nome real, seja qual for).
        foreach ($indexes as $idx) {
            if (!empty($idx['unique']) && ($idx['columns'] ?? []) === [$col]) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$idx['name']}`");
            }
        }

        Schema::table($table, function (Blueprint $t) use ($col, $newName) {
            $t->unique(['tenant_id', $col], $newName);
        });
    }

    private function dropCompositeRestoreGlobal(string $table, string $col, string $newName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        $indexes = collect(Schema::getIndexes($table));
        if ($indexes->contains(fn ($i) => $i['name'] === $newName)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$newName}`");
        }
        Schema::table($table, function (Blueprint $t) use ($col) {
            $t->unique($col);
        });
    }
};
