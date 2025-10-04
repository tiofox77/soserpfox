<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Resetar cached roles e permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Super Admin
            'tenants.view', 'tenants.create', 'tenants.edit', 'tenants.delete',
            'modules.manage', 'plans.manage', 'billing.manage',
            
            // Utilizadores
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.permissions',
            
            // Faturação - Clientes
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            
            // Faturação - Produtos
            'products.view', 'products.create', 'products.edit', 'products.delete',
            
            // Faturação - Faturas
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.issue',
            
            // Faturação - Pagamentos
            'payments.view', 'payments.create',
            
            // Faturação - Relatórios
            'invoices.reports', 'invoices.export',
            
            // RH - Colaboradores
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            
            // RH - Outros
            'attendance.manage', 'payroll.process', 'rh.reports',
            
            // Contabilidade
            'accounting.view', 'accounting.create', 'accounting.edit', 
            'accounting.chart', 'accounting.reports',
            
            // Oficina - Veículos
            'vehicles.view', 'vehicles.create', 'vehicles.edit',
            
            // Oficina - Reparações
            'repairs.view', 'repairs.create', 'repairs.edit', 'repairs.complete',
            
            // Oficina - Agendamentos
            'appointments.manage',
            
            // Configurações
            'settings.view', 'settings.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
