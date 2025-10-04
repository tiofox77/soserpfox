<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Resetar cached roles e permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Roles de sistema (globais - sem tenant_id)
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $manager = Role::firstOrCreate(['name' => 'Gestor']);
        $user = Role::firstOrCreate(['name' => 'Utilizador']);

        // Atribuir permissões ao Super Admin (todas)
        $superAdmin->givePermissionTo(Permission::all());

        // Atribuir permissões ao Admin (exceto super admin permissions)
        $adminPerms = Permission::where('name', 'not like', 'tenants.%')
            ->where('name', 'not like', 'modules.%')
            ->where('name', 'not like', 'plans.%')
            ->where('name', 'not like', 'billing.%')
            ->get();
        $admin->syncPermissions($adminPerms);

        // Atribuir permissões ao Gestor
        $managerPerms = [
            'users.view', 'users.create', 'users.edit',
            'customers.view', 'customers.create', 'customers.edit',
            'products.view', 'products.create', 'products.edit',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.issue',
            'payments.view', 'payments.create',
            'invoices.reports', 'invoices.export',
            'employees.view', 'employees.create', 'employees.edit',
            'attendance.manage', 'rh.reports',
            'accounting.view', 'accounting.reports',
            'vehicles.view', 'vehicles.create', 'vehicles.edit',
            'repairs.view', 'repairs.create', 'repairs.edit', 'repairs.complete',
            'appointments.manage',
            'settings.view',
        ];
        $manager->syncPermissions($managerPerms);

        // Atribuir permissões ao Utilizador (apenas visualização)
        $userPerms = [
            'customers.view', 'products.view', 'invoices.view', 'payments.view',
            'employees.view', 'vehicles.view', 'repairs.view',
        ];
        $user->syncPermissions($userPerms);
    }
}
