<?php

namespace App\Console\Commands\Accounting;

use Illuminate\Console\Command;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Account;
use App\Models\Tenant;

class SyncJournals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:sync-journals {--tenant= : ID do tenant específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar diários contabilísticos padrão (13 diários)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('');
        $this->info('========================================');
        $this->info('  SINCRONIZAR DIÁRIOS CONTABILÍSTICOS');
        $this->info('========================================');
        $this->info('');
        
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant #{$tenantId} não encontrado!");
                return 1;
            }
            $tenants = collect([$tenant]);
            $this->info("Processando apenas Tenant: {$tenant->name}");
        } else {
            $tenants = Tenant::where('is_active', true)->get();
            $this->info("Processando todos os tenants ativos ({$tenants->count()})");
        }
        
        $this->info('');
        
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalDeleted = 0;
        
        foreach ($tenants as $tenant) {
            $this->line("📊 Tenant: <fg=cyan>{$tenant->name}</>");
            
            // Buscar contas para defaults (se existirem)
            $caixaConta = Account::where('tenant_id', $tenant->id)->where('code', '11')->first();
            $bancoConta = Account::where('tenant_id', $tenant->id)->where('code', '12')->first();
            $clientesConta = Account::where('tenant_id', $tenant->id)->where('code', '21')->first();
            $fornecedoresConta = Account::where('tenant_id', $tenant->id)->where('code', '31')->first();
            
            $journals = $this->getJournalDefinitions($caixaConta, $bancoConta, $clientesConta, $fornecedoresConta);
            
            // Remover diários antigos sem lançamentos
            $deleted = Journal::where('tenant_id', $tenant->id)
                ->doesntHave('moves')
                ->whereNotIn('code', array_column($journals, 'code'))
                ->delete();
            
            $totalDeleted += $deleted;
            
            if ($deleted > 0) {
                $this->line("   🗑️  Removidos: <fg=red>{$deleted}</> diários sem lançamentos");
            }
            
            // Criar/atualizar diários padrão
            $created = 0;
            $updated = 0;
            
            foreach ($journals as $journalData) {
                $existing = Journal::where('tenant_id', $tenant->id)
                    ->where('code', $journalData['code'])
                    ->first();
                
                if ($existing) {
                    $existing->update($journalData);
                    $updated++;
                } else {
                    Journal::create(array_merge($journalData, ['tenant_id' => $tenant->id]));
                    $created++;
                }
            }
            
            $totalCreated += $created;
            $totalUpdated += $updated;
            
            $this->line("   ✅ Criados: <fg=green>{$created}</>");
            $this->line("   🔄 Atualizados: <fg=yellow>{$updated}</>");
            $this->info('');
        }
        
        // Resumo final
        $this->info('========================================');
        $this->info('  RESUMO');
        $this->info('========================================');
        $this->line("Tenants processados: <fg=cyan>{$tenants->count()}</>");
        $this->line("Diários criados: <fg=green>{$totalCreated}</>");
        $this->line("Diários atualizados: <fg=yellow>{$totalUpdated}</>");
        $this->line("Diários removidos: <fg=red>{$totalDeleted}</>");
        $this->info('========================================');
        $this->info('');
        $this->info('✨ Sincronização concluída com sucesso!');
        $this->info('');
        
        return 0;
    }
    
    /**
     * Definições dos diários padrão
     */
    private function getJournalDefinitions($caixaConta, $bancoConta, $clientesConta, $fornecedoresConta): array
    {
        return [
            // Diários Principais
            [
                'code' => '01',
                'name' => 'Diário Geral',
                'type' => 'general',
                'sequence_prefix' => 'DG-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '02',
                'name' => 'Diário de Caixa',
                'type' => 'cash',
                'sequence_prefix' => 'CX-',
                'last_number' => 0,
                'default_debit_account_id' => $caixaConta?->id,
                'default_credit_account_id' => $caixaConta?->id,
                'active' => true,
            ],
            [
                'code' => '03',
                'name' => 'Diário de Bancos',
                'type' => 'bank',
                'sequence_prefix' => 'BC-',
                'last_number' => 0,
                'default_debit_account_id' => $bancoConta?->id,
                'default_credit_account_id' => $bancoConta?->id,
                'active' => true,
            ],
            [
                'code' => '04',
                'name' => 'Diário de Vendas',
                'type' => 'sale',
                'sequence_prefix' => 'VD-',
                'last_number' => 0,
                'default_debit_account_id' => $clientesConta?->id,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '05',
                'name' => 'Diário de Compras',
                'type' => 'purchase',
                'sequence_prefix' => 'CP-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => $fornecedoresConta?->id,
                'active' => true,
            ],
            
            // Diários de Controle e Gestão
            [
                'code' => '06',
                'name' => 'Diário de Salários e Ordenados',
                'type' => 'payroll',
                'sequence_prefix' => 'SAL-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '07',
                'name' => 'Diário de IVA',
                'type' => 'tax',
                'sequence_prefix' => 'IVA-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '08',
                'name' => 'Diário de Depreciações e Amortizações',
                'type' => 'depreciation',
                'sequence_prefix' => 'DEP-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            
            // Diários Especiais
            [
                'code' => '09',
                'name' => 'Diário de Operações Diversas',
                'type' => 'miscellaneous',
                'sequence_prefix' => 'OD-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '10',
                'name' => 'Diário de Ajustes e Correções',
                'type' => 'adjustment',
                'sequence_prefix' => 'AJ-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '11',
                'name' => 'Diário de Regularização',
                'type' => 'regularization',
                'sequence_prefix' => 'REG-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '12',
                'name' => 'Diário de Abertura',
                'type' => 'opening',
                'sequence_prefix' => 'ABT-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '13',
                'name' => 'Diário de Encerramento',
                'type' => 'closing',
                'sequence_prefix' => 'ENC-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
        ];
    }
}
