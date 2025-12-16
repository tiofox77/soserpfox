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
    
    public function syncJournals()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Executar comando de sincronização
            \Artisan::call('accounting:sync-journals', ['--tenant' => $tenantId]);
            
            session()->flash('success', '✅ 13 diários contabilísticos sincronizados com sucesso!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao sincronizar diários: ' . $e->getMessage());
        }
    }
    
    public function syncTaxes()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Rodar seeder de impostos
            $taxSeeder = new \Database\Seeders\Accounting\TaxSeeder();
            $taxSeeder->seedForTenant($tenantId);
            
            session()->flash('success', '✅ Impostos padrão sincronizados com sucesso!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao sincronizar impostos: ' . $e->getMessage());
        }
    }
    
    public function syncCostCenters()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Rodar seeder de centros de custo
            $ccSeeder = new \Database\Seeders\CostCenterSeeder();
            $ccSeeder->seedForTenant($tenantId);
            
            session()->flash('success', '✅ Centros de custo sincronizados com sucesso!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao sincronizar centros de custo: ' . $e->getMessage());
        }
    }
    
    public function deleteAllAccountingData()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            DB::beginTransaction();
            
            // Apagar apenas se não houver lançamentos
            $movesCount = DB::table('accounting_moves')->where('tenant_id', $tenantId)->count();
            
            if ($movesCount > 0) {
                session()->flash('error', "Não é possível apagar dados contabilísticos pois existem {$movesCount} lançamentos registados.");
                return;
            }
            
            // Apagar diários sem lançamentos
            DB::table('accounting_journals')
                ->where('tenant_id', $tenantId)
                ->delete();
            
            // Apagar contas
            DB::table('accounting_accounts')
                ->where('tenant_id', $tenantId)
                ->delete();
            
            // Apagar impostos
            DB::table('accounting_taxes')
                ->where('tenant_id', $tenantId)
                ->delete();
            
            // Apagar centros de custo
            DB::table('cost_centers')
                ->where('tenant_id', $tenantId)
                ->delete();
            
            DB::commit();
            
            session()->flash('success', '✅ Dados contabilísticos apagados com sucesso! Pode agora importar novos dados.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Erro ao apagar dados: ' . $e->getMessage());
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
    
    public function importDocumentTypes()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Rodar seeder de tipos de documentos
            $seeder = new \Database\Seeders\Accounting\DocumentTypeSeeder();
            $seeder->runForTenant($tenantId);
            
            session()->flash('success', '✅ Tipos de documentos contabilísticos importados com sucesso do Excel!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao importar tipos de documentos: ' . $e->getMessage());
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
            'documentTypes' => DB::table('accounting_document_types')->where('tenant_id', $tenantId)->count(),
        ];
        
        // Buscar tenant para pegar status da integração
        $tenant = \App\Models\Tenant::find($tenantId);
        
        return view('livewire.accounting.settings.settings', [
            'stats' => $stats,
            'integrationEnabled' => $tenant->accounting_integration_enabled ?? false,
        ]);
    }
}
