<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Module;
use Illuminate\Console\Command;

class ResyncTenantModules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:resync-tenant-modules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ressincroniza módulos dos tenants baseado no plano ativo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Ressincronizando módulos dos tenants...');
        $this->newLine();
        
        $tenants = Tenant::with('activeSubscription.plan')->get();
        $synced = 0;
        $skipped = 0;
        
        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->name} (ID: {$tenant->id})");
            
            $subscription = $tenant->activeSubscription;
            
            if (!$subscription || !$subscription->plan) {
                $this->warn("  ⚠ Sem plano ativo - pulando");
                $skipped++;
                continue;
            }
            
            $plan = $subscription->plan;
            $this->info("  Plano: {$plan->name}");
            
            // Módulos do plano COM dependências (pivot como fonte de verdade;
            // o JSON included_modules chegou a ter slugs inexistentes, ex.
            // 'faturacao' em vez de 'invoicing').
            // Já não se faz detach(): isso apagava o histórico do pivot e tirava
            // a Tesouraria aos planos que não a listam.
            $slugs = $plan->moduleSlugsWithDependencies();

            if (!empty($slugs)) {
                $sync = new \App\Services\Tenant\TenantModuleSyncService();

                // Desactivar o que já não pertence ao plano (o serviço protege
                // dependências activas)
                $activos = $tenant->modules()->wherePivot('is_active', true)->pluck('modules.slug')->toArray();
                foreach (array_diff($activos, $slugs) as $slug) {
                    $sync->deactivateModule($tenant, $slug);
                    $this->line("    − {$slug} (fora do plano)");
                }

                foreach ($slugs as $slug) {
                    $sync->activateModule($tenant, $slug);
                    $this->info("    ✓ {$slug}");
                }
                $synced++;
            } else {
                $this->warn("  ⚠ Plano sem módulos definidos");
                $skipped++;
            }
            
            $this->newLine();
        }
        
        $this->info("Concluído!");
        $this->info("Tenants sincronizados: {$synced}");
        $this->info("Tenants pulados: {$skipped}");
        
        return Command::SUCCESS;
    }
}
