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
            
            // Remover todos os módulos antigos
            $tenant->modules()->detach();
            
            // Adicionar módulos do plano
            if ($plan->included_modules && is_array($plan->included_modules)) {
                foreach ($plan->included_modules as $moduleSlug) {
                    $module = Module::where('slug', $moduleSlug)->first();
                    
                    if ($module) {
                        $tenant->modules()->attach($module->id, [
                            'is_active' => true,
                            'activated_at' => now(),
                        ]);
                        $this->info("    ✓ {$module->name} ({$moduleSlug})");
                    } else {
                        $this->error("    ✗ Módulo '{$moduleSlug}' não encontrado");
                    }
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
