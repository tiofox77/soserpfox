<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class MigrationHelper
{
    /**
     * Executa uma migration de forma segura, ignorando erros se já foi aplicada
     * 
     * @param callable $callback
     * @param string $migrationName
     * @return bool
     */
    public static function runSafely(callable $callback, string $migrationName = 'Unknown'): bool
    {
        try {
            $callback();
            return true;
        } catch (\Exception $e) {
            Log::warning("Migration {$migrationName} skipped: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se uma tabela existe antes de executar
     * 
     * @param string $table
     * @param callable $callback
     * @return bool
     */
    public static function ifTableExists(string $table, callable $callback): bool
    {
        if (!Schema::hasTable($table)) {
            Log::info("Table {$table} does not exist. Skipping migration.");
            return false;
        }

        try {
            $callback();
            return true;
        } catch (\Exception $e) {
            Log::warning("Migration on table {$table} failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se uma coluna existe antes de executar
     * 
     * @param string $table
     * @param string $column
     * @param callable $callback
     * @return bool
     */
    public static function ifColumnExists(string $table, string $column, callable $callback): bool
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            Log::info("Column {$table}.{$column} does not exist. Skipping migration.");
            return false;
        }

        try {
            $callback();
            return true;
        } catch (\Exception $e) {
            Log::warning("Migration on column {$table}.{$column} failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se uma coluna NÃO existe antes de adicionar
     * 
     * @param string $table
     * @param string $column
     * @param callable $callback
     * @return bool
     */
    public static function ifColumnNotExists(string $table, string $column, callable $callback): bool
    {
        if (!Schema::hasTable($table)) {
            Log::warning("Table {$table} does not exist. Cannot add column.");
            return false;
        }

        if (Schema::hasColumn($table, $column)) {
            Log::info("Column {$table}.{$column} already exists. Skipping migration.");
            return false;
        }

        try {
            $callback();
            return true;
        } catch (\Exception $e) {
            Log::warning("Failed to add column {$table}.{$column}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um ENUM de forma segura, verificando se já foi atualizado
     * 
     * @param string $table
     * @param string $column
     * @param array $newValues
     * @param string $comment
     * @return bool
     */
    public static function updateEnumSafely(string $table, string $column, array $newValues, string $comment = ''): bool
    {
        try {
            if (!Schema::hasTable($table)) {
                Log::info("Table {$table} does not exist. Skipping ENUM update.");
                return false;
            }

            // Obter a definição atual da coluna
            $columnInfo = DB::select("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);
            
            if (empty($columnInfo)) {
                Log::warning("Column {$table}.{$column} does not exist.");
                return false;
            }

            $currentType = $columnInfo[0]->Type;
            
            // Verificar se já contém todos os novos valores
            $allValuesExist = true;
            foreach ($newValues as $value) {
                if (!str_contains($currentType, "'{$value}'")) {
                    $allValuesExist = false;
                    break;
                }
            }

            if ($allValuesExist) {
                Log::info("ENUM {$table}.{$column} already up to date. Skipping.");
                return false;
            }

            // Remover duplicados e criar valores únicos
            $uniqueValues = array_unique($newValues);
            $enumValues = implode("', '", $uniqueValues);
            
            $commentSQL = $comment ? "COMMENT '{$comment}'" : '';
            
            DB::statement("ALTER TABLE {$table} MODIFY COLUMN {$column} ENUM('{$enumValues}') {$commentSQL}");
            
            Log::info("ENUM {$table}.{$column} updated successfully.");
            return true;
            
        } catch (\Exception $e) {
            Log::warning("Failed to update ENUM {$table}.{$column}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adiciona um índice de forma segura (ignora se já existe)
     * 
     * @param string $table
     * @param string|array $columns
     * @param string $indexName
     * @return bool
     */
    public static function addIndexSafely(string $table, $columns, string $indexName = null): bool
    {
        try {
            if (!Schema::hasTable($table)) {
                return false;
            }

            $columns = is_array($columns) ? $columns : [$columns];
            $indexName = $indexName ?? $table . '_' . implode('_', $columns) . '_index';

            // Verificar se o índice já existe
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            
            if (!empty($indexes)) {
                Log::info("Index {$indexName} already exists on {$table}. Skipping.");
                return false;
            }

            Schema::table($table, function ($table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });

            return true;
            
        } catch (\Exception $e) {
            Log::warning("Failed to add index {$indexName} on {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove um índice de forma segura (ignora se não existe)
     * 
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    public static function dropIndexSafely(string $table, string $indexName): bool
    {
        try {
            if (!Schema::hasTable($table)) {
                return false;
            }

            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            
            if (empty($indexes)) {
                Log::info("Index {$indexName} does not exist on {$table}. Skipping.");
                return false;
            }

            Schema::table($table, function ($table) use ($indexName) {
                $table->dropIndex($indexName);
            });

            return true;
            
        } catch (\Exception $e) {
            Log::warning("Failed to drop index {$indexName} on {$table}: " . $e->getMessage());
            return false;
        }
    }
}
