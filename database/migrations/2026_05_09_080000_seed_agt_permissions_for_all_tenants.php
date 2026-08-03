<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante que invoicing.agt.view / edit existem GLOBALMENTE
 * e estao atribuidas as roles padrao (Super Admin/Admin/Gestor/Administrador Faturacao)
 * em TODOS os tenants existentes.
 *
 * Idempotente — pode correr varias vezes sem efeito secundario.
 *
 * Para tenants novos: o RoleHelper::createDefaultRolesForTenant() ja usa
 * Permission::all(), portanto vai apanhar automaticamente estas permissoes
 * na criacao de novos tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Garantir que as permissoes globais existem
        $permissions = [
            'invoicing.agt.view' => 'Ver Configuracoes AGT Angola',
            'invoicing.agt.edit' => 'Editar Configuracoes AGT Angola',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        // 2. Atribuir a roles padrao em TODOS tenants existentes
        $rolesPadrao = [
            'Super Admin', 'Admin', 'Gestor',
            'Administrador Faturacao', 'Administrador Faturação',
        ];

        // Verificar se a tabela tenants existe (compat. fresh installs)
        if (!\Schema::hasTable('tenants')) {
            return;
        }

        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            $roles = Role::where('roles.tenant_id', $tenant->id)
                ->whereIn('name', $rolesPadrao)
                ->get();

            foreach ($roles as $role) {
                foreach (array_keys($permissions) as $permName) {
                    if (!$role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nao fazer rollback — permissoes sao seeded
    }
};
