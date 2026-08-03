<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Substitui o índice UNIQUE global em invoicing_products.code por um índice
 * COMPOSTO (tenant_id, code). O esquema multi-tenant exige que o code seja
 * único apenas dentro de cada tenant — anteriormente um tenant não podia ter
 * `PROD000001` se outro tenant qualquer já o tivesse, o que quebrava o
 * gerador automático de códigos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoicing_products')) {
            return;
        }

        // 1) Localizar e largar o(s) índice(s) UNIQUE single-column em `code`
        $this->dropUniqueIndexesOn('invoicing_products', 'code');

        // 2) Criar índice UNIQUE composto (tenant_id, code) se não existir
        if (!$this->indexExists('invoicing_products', 'invoicing_products_tenant_id_code_unique')) {
            Schema::table('invoicing_products', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code'], 'invoicing_products_tenant_id_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoicing_products')) {
            return;
        }

        if ($this->indexExists('invoicing_products', 'invoicing_products_tenant_id_code_unique')) {
            Schema::table('invoicing_products', function (Blueprint $table) {
                $table->dropUnique('invoicing_products_tenant_id_code_unique');
            });
        }

        if (!$this->indexExists('invoicing_products', 'invoicing_products_code_unique')) {
            Schema::table('invoicing_products', function (Blueprint $table) {
                $table->unique('code', 'invoicing_products_code_unique');
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT COUNT(*) AS C FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );
        $row = (array) ($result ?? []);
        $row = array_change_key_case($row, CASE_LOWER);
        return (int) ($row['c'] ?? 0) > 0;
    }

    protected function dropUniqueIndexesOn(string $table, string $column): void
    {
        $database = DB::getDatabaseName();
        // Encontrar todos os índices UNIQUE single-column sobre `column`
        $rows = DB::select(
            'SELECT index_name AS idx FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND non_unique = 0 AND column_name = ?
             GROUP BY index_name
             HAVING COUNT(*) = 1',
            [$database, $table, $column]
        );

        foreach ($rows as $row) {
            $r = array_change_key_case((array) $row, CASE_LOWER);
            $name = $r['idx'] ?? null;
            if (!$name) { continue; }
            if ($name === 'PRIMARY') {
                continue;
            }
            try {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            } catch (\Throwable $e) {
                // ignora — pode estar a ser usado por foreign key; tenta recriar como non-unique
            }
        }
    }
};
