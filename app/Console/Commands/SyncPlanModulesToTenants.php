<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Module;
use Illuminate\Console\Command;

class SyncPlanModulesToTenants extends Command
{
    protected $signature = 'plan:sync-modules 
                            {plan_id? : ID do plano específico}
                            {--module= : Sincronizar apenas um módulo específico (slug)}
                            {--all : Sincronizar todos os planos}';

    protected $description = 'Sincronizar módulos dos planos com os tenants que têm subscription ativa';

    public function handle()
    {
        $planId = $this->argument('plan_id');
        $moduleSlug = $this->option('module');
        $syncAll = $this->option('all');

        // Sincronizar todos os planos
        if ($syncAll) {
            return $this->syncAllPlans($moduleSlug);
        }

        // Sincronizar plano específico
        if ($planId) {
            return $this->syncPlan($planId, $moduleSlug);
        }

        $this->error('❌ Especifique um plano ou use --all para sincronizar todos');
        return 1;
    }

    /**
     * Sincronizar todos os planos
     */
    protected function syncAllPlans($moduleSlug = null)
    {
        $plans = Plan::where('is_active', true)->get();

        if ($plans->isEmpty()) {
            $this->warn('⚠️  Nenhum plano ativo encontrado');
            return 0;
        }

        $this->info("🔄 Sincronizando {$plans->count()} plano(s)...\n");

        $totalSynced = 0;

        foreach ($plans as $plan) {
            $this->line("📦 Plano: {$plan->name}");
            
            if ($moduleSlug) {
                $count = $this->syncPlanModule($plan, $moduleSlug);
            } else {
                $count = $plan->syncModulesToTenants();
            }

            if ($count > 0) {
                $this->info("   ✅ {$count} tenant(s) sincronizado(s)");
                $totalSynced += $count;
            } else {
                $this->line("   ⏭️  Nenhum tenant para sincronizar");
            }
        }

        $this->newLine();
        $this->info("✅ Total: {$totalSynced} tenant(s) sincronizados em {$plans->count()} plano(s)");

        return 0;
    }

    /**
     * Sincronizar um plano específico
     */
    protected function syncPlan($planId, $moduleSlug = null)
    {
        $plan = Plan::find($planId);

        if (!$plan) {
            $this->error("❌ Plano ID {$planId} não encontrado");
            return 1;
        }

        $this->info("🔄 Sincronizando plano: {$plan->name}\n");

        if ($moduleSlug) {
            $count = $this->syncPlanModule($plan, $moduleSlug);
        } else {
            $count = $plan->syncModulesToTenants();
        }

        if ($count > 0) {
            $this->info("✅ {$count} tenant(s) sincronizados com sucesso!");
        } else {
            $this->warn("⚠️  Nenhum tenant para sincronizar");
        }

        return 0;
    }

    /**
     * Sincronizar módulo específico de um plano
     */
    protected function syncPlanModule(Plan $plan, $moduleSlug)
    {
        $module = Module::where('slug', $moduleSlug)->first();

        if (!$module) {
            $this->error("   ❌ Módulo '{$moduleSlug}' não encontrado");
            return 0;
        }

        $count = $plan->syncModuleToTenants($module->id);

        if ($count > 0) {
            $this->info("   ✅ Módulo '{$module->name}' sincronizado com {$count} tenant(s)");
        } else {
            $this->warn("   ⚠️  Módulo '{$module->name}' não sincronizado (não está no plano ou sem tenants)");
        }

        return $count;
    }
}
