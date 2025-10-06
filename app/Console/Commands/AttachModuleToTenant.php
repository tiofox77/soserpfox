<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Console\Command;

class AttachModuleToTenant extends Command
{
    protected $signature = 'module:attach {module_slug} {tenant_id?}';
    protected $description = 'Vincular módulo a um tenant específico ou a todos';

    public function handle()
    {
        $moduleSlug = $this->argument('module_slug');
        $tenantId = $this->argument('tenant_id');

        $module = Module::where('slug', $moduleSlug)->first();

        if (!$module) {
            $this->error("❌ Módulo '{$moduleSlug}' não encontrado!");
            return 1;
        }

        if ($tenantId) {
            // Vincular a um tenant específico
            $tenant = Tenant::find($tenantId);
            
            if (!$tenant) {
                $this->error("❌ Tenant ID {$tenantId} não encontrado!");
                return 1;
            }

            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['is_active' => true]
            ]);

            $this->info("✅ Módulo '{$module->name}' vinculado ao tenant '{$tenant->name}'!");
        } else {
            // Vincular a todos os tenants
            $tenants = Tenant::all();
            
            foreach ($tenants as $tenant) {
                $tenant->modules()->syncWithoutDetaching([
                    $module->id => ['is_active' => true]
                ]);
            }

            $this->info("✅ Módulo '{$module->name}' vinculado a {$tenants->count()} tenant(s)!");
        }

        return 0;
    }
}
