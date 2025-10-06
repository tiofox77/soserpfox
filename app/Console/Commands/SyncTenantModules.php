<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class SyncTenantModules extends Command
{
    protected $signature = 'tenant:sync-modules {--email= : Email do usuário} {--tenant= : ID do tenant} {--all : Sincronizar todos os tenants}';
    protected $description = 'Sincronizar módulos do tenant baseado no plano ativo';

    public function handle()
    {
        if ($this->option('all')) {
            return $this->syncAll();
        }

        $email = $this->option('email');
        $tenantId = $this->option('tenant');

        if (!$email && !$tenantId) {
            $this->error('❌ Forneça --email ou --tenant ou use --all');
            return 1;
        }

        if ($email) {
            return $this->syncByEmail($email);
        }

        return $this->syncTenant($tenantId);
    }

    protected function syncByEmail($email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ Usuário não encontrado: {$email}");
            return 1;
        }

        $this->info("👤 Usuário: {$user->name}");
        $tenants = $user->tenants;

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Usuário não tem empresas');
            return 1;
        }

        foreach ($tenants as $tenant) {
            $this->syncTenant($tenant->id);
        }

        return 0;
    }

    protected function syncTenant($tenantId)
    {
        $tenant = Tenant::with('subscriptions.plan.modules', 'modules')->find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant não encontrado: {$tenantId}");
            return 1;
        }

        $this->newLine();
        $this->info("🏢 Empresa: {$tenant->name} (ID: {$tenant->id})");
        $this->line(str_repeat('-', 60));

        // Subscription ativa
        $subscription = $tenant->subscriptions()->where('status', 'active')->with('plan.modules')->first();

        if (!$subscription) {
            $this->warn('⚠️  Nenhuma subscription ativa');
            return 1;
        }

        $plan = $subscription->plan;
        $this->info("📦 Plano: {$plan->name}");

        // Módulos do plano
        $planModuleIds = $plan->modules()->pluck('modules.id')->toArray();
        
        // Módulos ativos atualmente
        $currentActiveIds = $tenant->modules()->wherePivot('is_active', true)->pluck('modules.id')->toArray();

        // Calcular diferenças
        $toDeactivate = array_diff($currentActiveIds, $planModuleIds);
        $toActivate = array_diff($planModuleIds, $currentActiveIds);

        if (empty($toDeactivate) && empty($toActivate)) {
            $this->info('✅ Módulos já sincronizados corretamente!');
            return 0;
        }

        // Mostrar mudanças
        if (!empty($toDeactivate)) {
            $this->warn("❌ Desativar: " . implode(', ', $toDeactivate));
        }
        if (!empty($toActivate)) {
            $this->info("✅ Ativar: " . implode(', ', $toActivate));
        }

        if (!$this->confirm('Sincronizar agora?', true)) {
            $this->warn('❌ Cancelado');
            return 0;
        }

        \DB::beginTransaction();
        try {
            // Desativar
            foreach ($toDeactivate as $moduleId) {
                $tenant->modules()->updateExistingPivot($moduleId, [
                    'is_active' => false,
                    'deactivated_at' => now(),
                ]);
                $moduleName = \App\Models\Module::find($moduleId)->name;
                $this->line("   ❌ Desativado: {$moduleName}");
            }

            // Ativar
            foreach ($toActivate as $moduleId) {
                $tenant->modules()->syncWithoutDetaching([
                    $moduleId => [
                        'is_active' => true,
                        'activated_at' => now(),
                        'deactivated_at' => null,
                    ]
                ]);
                $moduleName = \App\Models\Module::find($moduleId)->name;
                $this->line("   ✅ Ativado: {$moduleName}");
            }

            \DB::commit();
            $this->newLine();
            $this->info('✅ Sincronização concluída!');

            return 0;

        } catch (\Exception $e) {
            \DB::rollBack();
            $this->error("❌ Erro: {$e->getMessage()}");
            return 1;
        }
    }

    protected function syncAll()
    {
        $tenants = Tenant::with('subscriptions')->get();
        $this->info("📋 Sincronizando {$tenants->count()} empresas...");

        $synced = 0;
        foreach ($tenants as $tenant) {
            if ($this->syncTenant($tenant->id) === 0) {
                $synced++;
            }
        }

        $this->newLine();
        $this->info("✅ {$synced} empresas sincronizadas");
        return 0;
    }
}
