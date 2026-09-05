<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;

class AttachModuleToTenant extends Command
{
    protected $signature = 'module:attach
                            {module_slug? : Slug do módulo}
                            {tenant_id? : ID do tenant (compatibilidade)}
                            {--tenant= : ID do tenant}
                            {--all : Ativar todos os módulos no tenant indicado}';
    protected $description = 'Vincular módulo a um tenant específico ou a todos';

    public function handle(TenantModuleSyncService $sync): int
    {
        $moduleSlug = $this->argument('module_slug');
        $tenantId = $this->option('tenant') ?: $this->argument('tenant_id');

        if ($this->option('all')) {
            if (!$tenantId) {
                $this->error('A opção --all exige --tenant=ID.');

                return self::FAILURE;
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant ID {$tenantId} não encontrado!");

                return self::FAILURE;
            }

            $modules = Module::query()->orderBy('id')->get();
            foreach ($modules as $module) {
                $sync->activateModule($tenant, $module->slug);
                $this->line("  ✓ {$module->name} ({$module->slug})");
            }

            $this->info("Todos os {$modules->count()} módulos foram ativados para '{$tenant->name}'.");

            return self::SUCCESS;
        }

        if (!$moduleSlug) {
            $this->error('Informe module_slug ou use --all --tenant=ID.');

            return self::FAILURE;
        }

        $module = Module::where('slug', $moduleSlug)->first();

        if (!$module) {
            $this->error("❌ Módulo '{$moduleSlug}' não encontrado!");
            return self::FAILURE;
        }

        if ($tenantId) {
            // Vincular a um tenant específico
            $tenant = Tenant::find($tenantId);
            
            if (!$tenant) {
                $this->error("❌ Tenant ID {$tenantId} não encontrado!");
                return self::FAILURE;
            }

            $sync->activateModule($tenant, $module->slug);

            $this->info("✅ Módulo '{$module->name}' vinculado ao tenant '{$tenant->name}'!");
        } else {
            // Vincular a todos os tenants
            $tenants = Tenant::all();
            
            foreach ($tenants as $tenant) {
                $sync->activateModule($tenant, $module->slug);
            }

            $this->info("✅ Módulo '{$module->name}' vinculado a {$tenants->count()} tenant(s)!");
        }

        return self::SUCCESS;
    }
}
