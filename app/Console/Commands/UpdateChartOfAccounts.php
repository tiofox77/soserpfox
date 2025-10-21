<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Accounting\AccountSeeder;

class UpdateChartOfAccounts extends Command
{
    protected $signature = 'accounting:update-chart {--force : Forçar sem confirmação}';
    protected $description = 'Substituir plano de contas antigo pelo novo (importado do Excel)';

    public function handle()
    {
        $this->info('🔄 ATUALIZAÇÃO DO PLANO DE CONTAS');
        $this->newLine();
        
        // Verificar se arquivo importado existe
        $importedFile = database_path('seeders/Accounting/imported_accounts.php');
        
        if (!file_exists($importedFile)) {
            $this->error('❌ Arquivo de contas importadas não encontrado!');
            $this->warn('Execute primeiro: php artisan accounting:import-chart "Plano.xls"');
            return 1;
        }
        
        $newAccounts = require $importedFile;
        $this->info('✅ Arquivo importado encontrado: ' . count($newAccounts) . ' contas');
        $this->newLine();
        
        // Listar tenants
        $tenants = \App\Models\Tenant::where('is_active', true)->get();
        
        if ($tenants->isEmpty()) {
            $this->error('❌ Nenhum tenant ativo encontrado!');
            return 1;
        }
        
        $this->table(
            ['ID', 'Nome', 'Contas Atuais'],
            $tenants->map(function($tenant) {
                $count = DB::table('accounting_accounts')
                    ->where('tenant_id', $tenant->id)
                    ->count();
                return [$tenant->id, $tenant->name, $count];
            })
        );
        
        $this->newLine();
        $this->warn('⚠️  ATENÇÃO: Esta operação irá:');
        $this->line('  1. APAGAR todas as contas existentes de cada tenant');
        $this->line('  2. INSERIR as ' . count($newAccounts) . ' novas contas do Excel');
        $this->newLine();
        
        // Confirmação
        if (!$this->option('force')) {
            if (!$this->confirm('Deseja continuar?', false)) {
                $this->info('❌ Operação cancelada.');
                return 0;
            }
        }
        
        $this->newLine();
        $this->info('🚀 Iniciando atualização...');
        $this->newLine();
        
        foreach ($tenants as $tenant) {
            $this->info("📦 Processando: {$tenant->name}");
            
            try {
                DB::beginTransaction();
                
                // Contar contas antigas
                $oldCount = DB::table('accounting_accounts')
                    ->where('tenant_id', $tenant->id)
                    ->count();
                
                $this->line("  🗑️  Removendo {$oldCount} contas antigas...");
                
                // IMPORTANTE: Remover referências em journals antes de deletar
                $this->line("  🔗 Limpando referências em journals...");
                DB::table('accounting_journals')
                    ->where('tenant_id', $tenant->id)
                    ->update([
                        'default_debit_account_id' => null,
                        'default_credit_account_id' => null,
                    ]);
                
                // Remover referências em outras tabelas se existirem
                // accounting_journal_entries (se tiver FK)
                if (DB::getSchemaBuilder()->hasTable('accounting_journal_entries')) {
                    DB::table('accounting_journal_entries')
                        ->whereIn('journal_id', function($query) use ($tenant) {
                            $query->select('id')
                                  ->from('accounting_journals')
                                  ->where('tenant_id', $tenant->id);
                        })
                        ->update([
                            'debit_account_id' => null,
                            'credit_account_id' => null,
                        ]);
                }
                
                // Deletar contas antigas
                DB::table('accounting_accounts')
                    ->where('tenant_id', $tenant->id)
                    ->delete();
                
                $this->line('  ➕ Inserindo ' . count($newAccounts) . ' contas novas...');
                
                // Inserir novas contas
                $inserted = 0;
                foreach ($newAccounts as $account) {
                    DB::table('accounting_accounts')->insert(array_merge($account, [
                        'tenant_id' => $tenant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                    $inserted++;
                    
                    // Progress bar a cada 100 contas
                    if ($inserted % 100 === 0) {
                        $this->line("    ⏳ {$inserted}/" . count($newAccounts));
                    }
                }
                
                DB::commit();
                
                $this->info("  ✅ {$tenant->name}: {$oldCount} antigas → {$inserted} novas");
                $this->newLine();
                
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  ❌ Erro ao processar {$tenant->name}: {$e->getMessage()}");
                $this->newLine();
                
                if (!$this->confirm('Continuar com próximo tenant?', false)) {
                    return 1;
                }
            }
        }
        
        $this->newLine();
        $this->info('🎉 ATUALIZAÇÃO CONCLUÍDA!');
        $this->newLine();
        
        // Mostrar resumo final
        $this->info('📊 RESUMO FINAL:');
        $this->table(
            ['ID', 'Nome', 'Contas Atuais'],
            $tenants->map(function($tenant) {
                $count = DB::table('accounting_accounts')
                    ->where('tenant_id', $tenant->id)
                    ->count();
                return [$tenant->id, $tenant->name, $count];
            })
        );
        
        $this->newLine();
        $this->warn('⚠️  IMPORTANTE:');
        $this->line('  As contas padrão dos journals foram removidas.');
        $this->line('  Você precisa reconfigurar os journals em cada tenant:');
        $this->line('  - Acesse: Configurações > Contabilidade > Diários');
        $this->line('  - Configure novamente as contas de débito/crédito padrão');
        $this->newLine();
        
        return 0;
    }
}
