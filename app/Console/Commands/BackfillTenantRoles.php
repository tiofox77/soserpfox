<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Garante que cada tenant tem o conjunto canónico de 9 roles
 * (getDefaultRolePermissionMap) com as permissões corretas.
 *
 * Por defeito SÓ processa tenants a que falta pelo menos uma role canónica
 * (não mexe em tenants já completos). Use --all para re-sincronizar todos
 * (repõe eventuais desvios manuais para o canónico).
 *
 *   php artisan roles:backfill                 → só os incompletos
 *   php artisan roles:backfill --tenant=11     → apenas o tenant 11
 *   php artisan roles:backfill --all           → todos (re-sync canónico)
 */
class BackfillTenantRoles extends Command
{
    protected $signature = 'roles:backfill {--tenant= : ID de um tenant específico} {--all : Re-sincronizar TODOS os tenants}';

    protected $description = 'Cria/sincroniza as roles padrão em falta nos tenants';

    public function handle(): int
    {
        if (!function_exists('createDefaultRolesForTenant') || !function_exists('getDefaultRolePermissionMap')) {
            $this->error('Helpers de roles não carregados.');
            return self::FAILURE;
        }

        $expectedRoles = array_keys(getDefaultRolePermissionMap(Permission::all()));

        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }
        $tenants = $query->get(['id', 'name']);

        $processed = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $existing = Role::where('tenant_id', $tenant->id)->pluck('name')->toArray();
            $missing = array_diff($expectedRoles, $existing);

            // Sem --all e sem --tenant: saltar tenants já completos
            if (!$this->option('all') && !$this->option('tenant') && empty($missing)) {
                $this->line("• #{$tenant->id} {$tenant->name} — já completo, ignorado");
                $skipped++;
                continue;
            }

            createDefaultRolesForTenant($tenant->id);

            $now = Role::where('tenant_id', $tenant->id)->count();
            $created = empty($missing) ? 're-sync' : ('criadas ' . count($missing));
            $this->info("✓ #{$tenant->id} {$tenant->name} — {$created} (total agora: {$now} roles)");
            $processed++;
        }

        // Reset do contexto de equipa para não afetar o resto do processo
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }

        $this->newLine();
        $this->info("Concluído. Processados: {$processed} | Ignorados: {$skipped}");
        return self::SUCCESS;
    }
}
