<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Cria roles GLOBAIS (sem tenant_id) com filtros dinâmicos,
     * alinhados com createDefaultRolesForTenant() no RoleHelper.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = Permission::all();

        // ── Roles base (mesma lógica do RoleHelper) ──────────────
        $roleLevels = [
            'Super Admin' => [
                'permissions' => $allPermissions->pluck('name')->toArray(),
            ],
            'Admin' => [
                'permissions' => $allPermissions->filter(function ($perm) {
                    return !str_contains($perm->name, 'system.');
                })->pluck('name')->toArray(),
            ],
            'Gestor' => [
                'permissions' => $allPermissions->filter(function ($perm) {
                    return str_contains($perm->name, '.view')
                        || str_contains($perm->name, '.create')
                        || str_contains($perm->name, '.edit');
                })->pluck('name')->toArray(),
            ],
            'Utilizador' => [
                'permissions' => $allPermissions->filter(function ($perm) {
                    return str_contains($perm->name, '.view');
                })->pluck('name')->toArray(),
            ],
        ];

        // ── Roles especializados ─────────────────────────────────
        $specializedRoles = [
            'Administrador Faturação' => $allPermissions->filter(function ($perm) {
                return str_starts_with($perm->name, 'invoicing.')
                    || str_starts_with($perm->name, 'treasury.')
                    || str_starts_with($perm->name, 'customers.')
                    || str_starts_with($perm->name, 'products.')
                    || $perm->name === 'settings.view';
            })->pluck('name')->toArray(),

            'Vendedor' => $allPermissions->filter(function ($perm) {
                return in_array($perm->name, [
                    'customers.view', 'customers.create', 'customers.edit',
                    'products.view',
                    'invoicing.sales.invoices.create', 'invoicing.sales.invoices.view',
                    'invoicing.sales.proformas.create', 'invoicing.sales.proformas.view', 'invoicing.sales.proformas.edit',
                    'invoicing.pos.access', 'invoicing.pos.sell',
                    'invoicing.receipts.view', 'invoicing.receipts.create',
                ]) || $perm->name === 'invoicing.stock.view';
            })->pluck('name')->toArray(),

            'Caixa' => $allPermissions->filter(function ($perm) {
                return in_array($perm->name, [
                    'invoicing.pos.access', 'invoicing.pos.sell',
                    'invoicing.receipts.view', 'invoicing.receipts.create',
                    'invoicing.sales.invoices.view',
                    'customers.view',
                    'products.view',
                    'treasury.cash-registers.view', 'treasury.transactions.create',
                ]) || $perm->name === 'invoicing.stock.view';
            })->pluck('name')->toArray(),

            'Contabilista' => $allPermissions->filter(function ($perm) {
                return str_starts_with($perm->name, 'accounting.')
                    || $perm->name === 'invoicing.saft.view'
                    || str_starts_with($perm->name, 'treasury.')
                    && str_contains($perm->name, '.view');
            })->pluck('name')->toArray(),

            'Operador Stock' => $allPermissions->filter(function ($perm) {
                return str_starts_with($perm->name, 'invoicing.stock.')
                    || str_starts_with($perm->name, 'invoicing.warehouse')
                    || str_starts_with($perm->name, 'invoicing.inter-company-transfer.')
                    || str_starts_with($perm->name, 'invoicing.imports.')
                    || $perm->name === 'products.view'
                    || $perm->name === 'invoicing.suppliers.view';
            })->pluck('name')->toArray(),
        ];

        // Merge tudo
        foreach ($specializedRoles as $name => $perms) {
            $roleLevels[$name] = ['permissions' => $perms];
        }

        // ── Criar/sincronizar ────────────────────────────────────
        foreach ($roleLevels as $roleName => $config) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => null]
            );
            $permissions = Permission::whereIn('name', $config['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
