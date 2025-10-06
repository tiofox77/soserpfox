<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Helpers\MigrationHelper;

class FixMigrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrations:fix
                            {--check : Apenas verificar sem fazer alterações}
                            {--enum : Corrigir apenas ENUMs}
                            {--duplicates : Remover migrations duplicadas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige problemas comuns em migrations (ENUMs duplicados, colunas, etc)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Verificando e corrigindo migrations...');
        $this->newLine();

        $checkOnly = $this->option('check');
        $enumOnly = $this->option('enum');
        $duplicatesOnly = $this->option('duplicates');

        if ($checkOnly) {
            $this->warn('Modo de verificação (nenhuma alteração será feita)');
            $this->newLine();
        }

        // 1. Corrigir ENUM duplicados
        if (!$duplicatesOnly || $enumOnly) {
            $this->fixDuplicateEnums($checkOnly);
        }

        // 2. Verificar migrations duplicadas
        if (!$enumOnly || $duplicatesOnly) {
            $this->checkDuplicateMigrations($checkOnly);
        }

        // 3. Verificar integridade geral
        if (!$enumOnly && !$duplicatesOnly) {
            $this->checkGeneralIntegrity();
        }

        $this->newLine();
        $this->info('✅ Verificação concluída!');
    }

    /**
     * Corrige ENUMs com valores duplicados
     */
    protected function fixDuplicateEnums(bool $checkOnly = false)
    {
        $this->line('📋 Verificando ENUMs...');

        $tables = [
            'invoicing_series' => [
                'column' => 'document_type',
                'expected_values' => [
                    'invoice', 'proforma', 'receipt', 'credit_note', 'debit_note',
                    'pos', 'purchase', 'advance', 'FT', 'FS', 'FR', 'NC', 'ND',
                    'GT', 'FP', 'VD', 'GR', 'GC', 'RC'
                ]
            ]
        ];

        foreach ($tables as $table => $config) {
            if (!Schema::hasTable($table)) {
                $this->warn("  ⚠️  Tabela {$table} não existe");
                continue;
            }

            $column = $config['column'];
            $expectedValues = $config['expected_values'];

            // Obter definição atual
            $columnInfo = DB::select("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);

            if (empty($columnInfo)) {
                $this->warn("  ⚠️  Coluna {$table}.{$column} não existe");
                continue;
            }

            $currentType = $columnInfo[0]->Type;

            // Extrair valores atuais do ENUM
            preg_match_all("/'([^']+)'/", $currentType, $matches);
            $currentValues = $matches[1] ?? [];

            // Verificar duplicados
            $duplicates = array_diff_assoc($currentValues, array_unique($currentValues));

            if (!empty($duplicates)) {
                $this->error("  ❌ ENUM duplicado encontrado em {$table}.{$column}");
                $this->line("     Valores duplicados: " . implode(', ', array_unique($duplicates)));

                if (!$checkOnly) {
                    $this->line("     Corrigindo...");
                    
                    $uniqueValues = array_unique($expectedValues);
                    $enumValues = implode("', '", $uniqueValues);
                    
                    try {
                        DB::statement("ALTER TABLE {$table} MODIFY COLUMN {$column} ENUM('{$enumValues}') COMMENT 'Tipo de documento'");
                        $this->info("     ✅ Corrigido!");
                    } catch (\Exception $e) {
                        $this->error("     ❌ Erro ao corrigir: " . $e->getMessage());
                    }
                }
            } else {
                $this->info("  ✅ {$table}.{$column} - OK");
            }
        }
    }

    /**
     * Verifica migrations duplicadas na tabela migrations
     */
    protected function checkDuplicateMigrations(bool $checkOnly = false)
    {
        $this->line('📋 Verificando migrations duplicadas...');

        $duplicates = DB::table('migrations')
            ->select('migration', DB::raw('COUNT(*) as count'))
            ->groupBy('migration')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('  ✅ Nenhuma migration duplicada encontrada');
            return;
        }

        foreach ($duplicates as $duplicate) {
            $this->error("  ❌ Migration duplicada: {$duplicate->migration} ({$duplicate->count}x)");

            if (!$checkOnly) {
                // Manter apenas a mais recente
                $migrations = DB::table('migrations')
                    ->where('migration', $duplicate->migration)
                    ->orderBy('batch', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                $keepId = $migrations->first()->id;

                DB::table('migrations')
                    ->where('migration', $duplicate->migration)
                    ->where('id', '!=', $keepId)
                    ->delete();

                $this->info("     ✅ Removidas duplicatas (mantido ID {$keepId})");
            }
        }
    }

    /**
     * Verifica integridade geral do banco de dados
     */
    protected function checkGeneralIntegrity()
    {
        $this->line('📋 Verificando integridade geral...');

        $criticalTables = [
            'tenants',
            'users',
            'invoicing_series',
            'invoicing_products',
            'invoicing_sales_invoices',
            'invoicing_stocks',
            'invoicing_product_batches'
        ];

        $missingTables = [];

        foreach ($criticalTables as $table) {
            if (!Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            $this->error('  ❌ Tabelas críticas ausentes:');
            foreach ($missingTables as $table) {
                $this->line("     - {$table}");
            }
            $this->warn('     Execute: php artisan migrate');
        } else {
            $this->info('  ✅ Todas as tabelas críticas existem');
        }

        // Verificar migrations pendentes
        $this->line('📋 Verificando migrations pendentes...');
        
        try {
            $output = shell_exec('php artisan migrate:status 2>&1');
            
            if (str_contains($output, 'Pending')) {
                $this->warn('  ⚠️  Existem migrations pendentes');
                $this->line('     Execute: php artisan migrate');
            } else {
                $this->info('  ✅ Nenhuma migration pendente');
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠️  Não foi possível verificar status das migrations');
        }
    }
}
