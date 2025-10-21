<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupChartOfAccounts extends Command
{
    protected $signature = 'accounting:backup-chart';
    protected $description = 'Criar backup do plano de contas atual';

    public function handle()
    {
        $this->info('💾 BACKUP DO PLANO DE CONTAS');
        $this->newLine();
        
        $tenants = \App\Models\Tenant::where('is_active', true)->get();
        
        if ($tenants->isEmpty()) {
            $this->error('❌ Nenhum tenant ativo encontrado!');
            return 1;
        }
        
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = database_path('backups/accounting');
        
        // Criar diretório se não existir
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        foreach ($tenants as $tenant) {
            $this->info("📦 Backup: {$tenant->name}");
            
            $accounts = DB::table('accounting_accounts')
                ->where('tenant_id', $tenant->id)
                ->select('code', 'name', 'type', 'nature', 'level', 'is_view', 'blocked', 'parent_id', 'integration_key')
                ->orderBy('code')
                ->get();
            
            if ($accounts->isEmpty()) {
                $this->warn("  ⚠️  Nenhuma conta encontrada");
                continue;
            }
            
            // Converter para array
            $accountsArray = $accounts->map(function($account) {
                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'nature' => $account->nature,
                    'level' => $account->level,
                    'is_view' => (bool)$account->is_view,
                    'blocked' => (bool)$account->blocked,
                    'parent_id' => $account->parent_id,
                    'integration_key' => $account->integration_key,
                ];
            })->toArray();
            
            // Gerar PHP file
            $filename = "backup_{$tenant->id}_{$timestamp}.php";
            $filepath = "{$backupDir}/{$filename}";
            
            $content = "<?php\n\n";
            $content .= "// Backup do plano de contas\n";
            $content .= "// Tenant: {$tenant->name} (ID: {$tenant->id})\n";
            $content .= "// Data: " . now()->format('d/m/Y H:i:s') . "\n";
            $content .= "// Total de contas: " . count($accountsArray) . "\n\n";
            $content .= "return " . var_export($accountsArray, true) . ";\n";
            
            file_put_contents($filepath, $content);
            
            $this->info("  ✅ Backup salvo: {$filename} ({$accounts->count()} contas)");
        }
        
        $this->newLine();
        $this->info("💾 Backups salvos em: {$backupDir}");
        $this->newLine();
        
        return 0;
    }
}
