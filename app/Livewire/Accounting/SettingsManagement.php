<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class SettingsManagement extends Component
{
    public function importAccounts()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Verificar se já existem contas
            $existingCount = DB::table('accounting_accounts')
                ->where('tenant_id', $tenantId)
                ->count();
            
            if ($existingCount > 0) {
                session()->flash('error', "Já existem {$existingCount} contas cadastradas. Não é possível importar novamente.");
                return;
            }
            
            // Rodar seeder
            $accountSeeder = new \Database\Seeders\Accounting\AccountSeeder();
            $accountSeeder->runForTenant($tenantId);
            
            session()->flash('success', '✅ 71 contas do Plano SNC importadas com sucesso!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao importar contas: ' . $e->getMessage());
        }
    }
    
    public function importJournals()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Verificar se já existem diários
            $existingCount = DB::table('accounting_journals')
                ->where('tenant_id', $tenantId)
                ->count();
            
            if ($existingCount > 0) {
                session()->flash('error', "Já existem {$existingCount} diários cadastrados. Não é possível importar novamente.");
                return;
            }
            
            // Rodar seeder
            $journalSeeder = new \Database\Seeders\Accounting\JournalSeeder();
            $journalSeeder->runForTenant($tenantId);
            
            session()->flash('success', '✅ 6 diários contabilísticos importados com sucesso!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao importar diários: ' . $e->getMessage());
        }
    }
    
    public function importPeriods()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $currentYear = now()->year;
            
            // Verificar se já existem períodos para o ano atual
            $existingCount = DB::table('accounting_periods')
                ->where('tenant_id', $tenantId)
                ->whereYear('date_start', $currentYear)
                ->count();
            
            if ($existingCount > 0) {
                session()->flash('error', "Já existem {$existingCount} períodos cadastrados para {$currentYear}. Não é possível importar novamente.");
                return;
            }
            
            // Rodar seeder
            $periodSeeder = new \Database\Seeders\Accounting\PeriodSeeder();
            $periodSeeder->runForTenant($tenantId);
            
            session()->flash('success', "✅ 12 períodos de {$currentYear} importados com sucesso!");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao importar períodos: ' . $e->getMessage());
        }
    }
    
    public function toggleIntegration()
    {
        try {
            $tenant = \App\Models\Tenant::find(auth()->user()->tenant_id);
            $tenant->accounting_integration_enabled = !$tenant->accounting_integration_enabled;
            $tenant->save();
            
            $status = $tenant->accounting_integration_enabled ? 'ativada' : 'desativada';
            session()->flash('success', "✅ Integração automática {$status} com sucesso!");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao alterar configuração: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Contar registros existentes
        $stats = [
            'accounts' => DB::table('accounting_accounts')->where('tenant_id', $tenantId)->count(),
            'journals' => DB::table('accounting_journals')->where('tenant_id', $tenantId)->count(),
            'periods' => DB::table('accounting_periods')->where('tenant_id', $tenantId)->count(),
        ];
        
        // Buscar tenant para pegar status da integração
        $tenant = \App\Models\Tenant::find($tenantId);
        
        return view('livewire.accounting.settings.settings', [
            'stats' => $stats,
            'integrationEnabled' => $tenant->accounting_integration_enabled ?? false,
        ]);
    }
}
