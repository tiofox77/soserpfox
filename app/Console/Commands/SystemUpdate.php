<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:update {--force : Força a execução sem confirmação}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza o sistema: migrations, seeders, cache e gera log';

    private $logFile;
    private $updateLog = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->logFile = storage_path('logs/system-update-' . date('Y-m-d_H-i-s') . '.log');
        
        $this->info('═══════════════════════════════════════════════════');
        $this->info('   🚀 ATUALIZAÇÃO DO SISTEMA - SOS ERP');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();
        
        // Se não tem flag --force, perguntar o modo
        if (!$this->option('force')) {
            $this->displayModeSelection();
        }
        
        $this->log('Iniciando atualização do sistema em ' . now()->format('d/m/Y H:i:s'));
        
        // 1. Verificar migrations pendentes
        $this->checkMigrations();
        
        // 2. Executar migrations
        $this->runMigrations();
        
        // 3. Verificar e executar seeders novos
        $this->checkAndRunSeeders();
        
        // 4. Limpar cache
        $this->clearCache();
        
        // 5. Verificar integridade da BD
        $this->checkDatabaseIntegrity();
        
        // 6. Gerar log final
        $this->generateFinalLog();
        
        $this->newLine();
        $this->info('✅ Atualização concluída com sucesso!');
        $this->info('📄 Log salvo em: ' . $this->logFile);
        
        return 0;
    }
    
    private function checkMigrations()
    {
        $this->info('🔍 Verificando migrations pendentes...');
        
        $migrations = collect(File::files(database_path('migrations')))
            ->map(fn($file) => $file->getFilename())
            ->toArray();
        
        $ran = DB::table('migrations')->pluck('migration')->toArray();
        
        $pending = array_diff(array_map(fn($m) => str_replace('.php', '', $m), $migrations), $ran);
        
        if (empty($pending)) {
            $this->warn('  ⚠️  Nenhuma migration pendente');
            $this->log('Nenhuma migration pendente');
        } else {
            $this->info('  ✅ Encontradas ' . count($pending) . ' migrations pendentes:');
            foreach ($pending as $migration) {
                $this->line('     - ' . $migration);
                $this->log('Migration pendente: ' . $migration);
            }
        }
        
        $this->newLine();
    }
    
    private function runMigrations()
    {
        $this->info('📦 Executando migrations...');
        
        try {
            // Usar Artisan::call para capturar erros melhor
            $output = '';
            Artisan::call('migrate', ['--force' => true], $output);
            
            $this->info('  ✅ Comando de migration executado');
            $this->log('Migrations processadas');
            
        } catch (\Exception $e) {
            $this->warn('  ⚠️  Migration saltada: ' . $e->getMessage());
            $this->log('Migration saltada: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
    
    private function checkAndRunSeeders()
    {
        $this->info('🌱 Verificando seeders...');
        
        // Verificar se a tabela seeders existe
        $seedersTableExists = DB::getSchemaBuilder()->hasTable('seeders');
        
        $seeders = collect(File::files(database_path('seeders')))
            ->filter(fn($file) => $file->getFilename() !== 'DatabaseSeeder.php')
            ->map(fn($file) => str_replace('.php', '', $file->getFilename()))
            ->toArray();
        
        if (empty($seeders)) {
            $this->warn('  ⚠️  Nenhum seeder encontrado');
            $this->log('Nenhum seeder encontrado');
        } else {
            // Obter seeders já executados
            $executedSeeders = $seedersTableExists 
                ? DB::table('seeders')->pluck('seeder')->toArray() 
                : [];
            
            // Filtrar apenas seeders novos (não executados)
            $pendingSeeders = array_diff($seeders, $executedSeeders);
            
            if (empty($pendingSeeders)) {
                $this->info('  ✅ Total de seeders: ' . count($seeders));
                $this->info('  ✅ Seeders executados: ' . count($executedSeeders));
                $this->warn('  ⚠️  Nenhum seeder novo para executar');
                $this->log('Nenhum seeder novo para executar');
            } else {
                $this->info('  ✅ Encontrados ' . count($pendingSeeders) . ' seeders novos:');
                
                // Obter próximo batch
                $batch = $seedersTableExists 
                    ? (DB::table('seeders')->max('batch') ?? 0) + 1 
                    : 1;
                
                foreach ($pendingSeeders as $seeder) {
                    $this->line('     - ' . $seeder);
                    
                    if ($this->option('force') || $this->confirm('     Executar ' . $seeder . '?', false)) {
                        try {
                            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
                            
                            // Registrar seeder executado
                            if ($seedersTableExists) {
                                DB::table('seeders')->insert([
                                    'seeder' => $seeder,
                                    'batch' => $batch,
                                    'executed_at' => now(),
                                ]);
                            }
                            
                            $this->info('     ✅ Seeder executado com sucesso');
                            $this->log('Seeder executado: ' . $seeder);
                        } catch (\Exception $e) {
                            $this->error('     ❌ Erro: ' . $e->getMessage());
                            $this->log('ERRO ao executar seeder ' . $seeder . ': ' . $e->getMessage());
                        }
                    } else {
                        $this->line('     ⏭️  Saltado');
                        $this->log('Seeder saltado: ' . $seeder);
                    }
                }
            }
        }
        
        $this->newLine();
    }
    
    private function clearCache()
    {
        $this->info('🧹 Limpando cache...');
        
        try {
            $this->call('optimize:clear');
            $this->log('Cache limpo com sucesso');
            $this->info('  ✅ Cache limpo com sucesso');
        } catch (\Exception $e) {
            $this->error('  ❌ Erro ao limpar cache: ' . $e->getMessage());
            $this->log('ERRO ao limpar cache: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
    
    private function checkDatabaseIntegrity()
    {
        $this->info('🔍 Verificando integridade da base de dados...');
        
        try {
            // Verificar conexão
            DB::connection()->getPdo();
            $this->info('  ✅ Conexão com BD: OK');
            $this->log('Conexão com BD: OK');
            
            // Contar tabelas
            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);
            $this->info('  ✅ Total de tabelas: ' . $tableCount);
            $this->log('Total de tabelas: ' . $tableCount);
            
            // Verificar migrations executadas
            $migrationsCount = DB::table('migrations')->count();
            $this->info('  ✅ Migrations executadas: ' . $migrationsCount);
            $this->log('Migrations executadas: ' . $migrationsCount);
            
        } catch (\Exception $e) {
            $this->error('  ❌ Erro na verificação: ' . $e->getMessage());
            $this->log('ERRO na verificação da BD: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
    
    private function generateFinalLog()
    {
        $this->info('📝 Gerando log de atualização...');
        
        $logContent = "═══════════════════════════════════════════════════\n";
        $logContent .= "   LOG DE ATUALIZAÇÃO DO SISTEMA - SOS ERP\n";
        $logContent .= "═══════════════════════════════════════════════════\n\n";
        $logContent .= "Data/Hora: " . now()->format('d/m/Y H:i:s') . "\n";
        $logContent .= "Usuário: " . (auth()->check() ? auth()->user()->name : 'Sistema') . "\n";
        $logContent .= "Versão PHP: " . PHP_VERSION . "\n";
        $logContent .= "Versão Laravel: " . app()->version() . "\n\n";
        $logContent .= "───────────────────────────────────────────────────\n\n";
        
        foreach ($this->updateLog as $log) {
            $logContent .= $log . "\n";
        }
        
        $logContent .= "\n───────────────────────────────────────────────────\n";
        $logContent .= "Atualização concluída em " . now()->format('d/m/Y H:i:s') . "\n";
        $logContent .= "═══════════════════════════════════════════════════\n";
        
        File::put($this->logFile, $logContent);
        
        $this->info('  ✅ Log salvo em: ' . $this->logFile);
        $this->log('Log de atualização gerado');
    }
    
    private function log($message)
    {
        $this->updateLog[] = '[' . now()->format('H:i:s') . '] ' . $message;
    }
    
    private function displayModeSelection()
    {
        $this->info('┌─────────────────────────────────────────────────┐');
        $this->info('│     🎯 MODO DE ATUALIZAÇÃO                      │');
        $this->info('└─────────────────────────────────────────────────┘');
        $this->newLine();
        
        $mode = $this->choice(
            '⚙️  Como deseja executar a atualização?',
            [
                'automatic' => '🚀 Automático - Executa tudo sem perguntar (recomendado)',
                'interactive' => '✋ Interativo - Pergunta antes de cada seeder',
                'cancel' => '❌ Cancelar atualização',
            ],
            'automatic'
        );
        
        $this->newLine();
        
        if ($mode === 'cancel') {
            $this->warn('❌ Atualização cancelada pelo usuário.');
            exit(0);
        }
        
        if ($mode === 'automatic') {
            $this->info('✅ Modo Automático selecionado');
            $this->info('   Todos os seeders novos serão executados automaticamente.');
            $this->input->setOption('force', true);
        } else {
            $this->info('✅ Modo Interativo selecionado');
            $this->info('   Você será consultado antes de executar cada seeder.');
        }
        
        $this->newLine();
        
        // Confirmação final
        if (!$this->confirm('📋 Iniciar atualização agora?', true)) {
            $this->warn('❌ Atualização cancelada pelo usuário.');
            exit(0);
        }
        
        $this->newLine();
        $this->info('─────────────────────────────────────────────────');
        $this->newLine();
    }
}
