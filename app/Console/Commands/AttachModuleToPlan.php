<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Console\Command;

class AttachModuleToPlan extends Command
{
    protected $signature = 'module:attach-plan {module_slug} {plan_id?}';
    protected $description = 'Vincular módulo a um plano específico ou a todos';

    public function handle()
    {
        $moduleSlug = $this->argument('module_slug');
        $planId = $this->argument('plan_id');

        $module = Module::where('slug', $moduleSlug)->first();

        if (!$module) {
            $this->error("❌ Módulo '{$moduleSlug}' não encontrado!");
            return 1;
        }

        if ($planId) {
            // Vincular a um plano específico
            $plan = Plan::find($planId);
            
            if (!$plan) {
                $this->error("❌ Plano ID {$planId} não encontrado!");
                return 1;
            }

            $plan->modules()->syncWithoutDetaching([$module->id]);

            $this->info("✅ Módulo '{$module->name}' vinculado ao plano '{$plan->name}'!");
        } else {
            // Vincular a todos os planos
            $plans = Plan::all();
            
            foreach ($plans as $plan) {
                $plan->modules()->syncWithoutDetaching([$module->id]);
            }

            $this->info("✅ Módulo '{$module->name}' vinculado a {$plans->count()} plano(s)!");
        }

        return 0;
    }
}
