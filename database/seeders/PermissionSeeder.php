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
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.permissions', 'users.manage',
            
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
            
            // Oficina/Workshop
            'vehicles.view', 'vehicles.create', 'vehicles.edit',
            'repairs.view', 'repairs.create', 'repairs.edit', 'repairs.complete',
            'appointments.manage',
            'workshop.dashboard', 'workshop.vehicles.view', 'workshop.vehicles.create', 'workshop.vehicles.edit',
            'workshop.mechanics.view', 'workshop.mechanics.create', 'workshop.mechanics.edit',
            'workshop.services.view', 'workshop.services.create', 'workshop.services.edit',
            'workshop.parts.view', 'workshop.parts.create', 'workshop.parts.edit',
            'workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.work-orders.edit', 'workshop.work-orders.delete',
            'workshop.reports.view',
            
            // Hotel
            'hotel.dashboard', 'hotel.settings.view', 'hotel.settings.edit',
            'hotel.room-types.view', 'hotel.room-types.create', 'hotel.room-types.edit', 'hotel.room-types.delete',
            'hotel.rooms.view', 'hotel.rooms.create', 'hotel.rooms.edit', 'hotel.rooms.delete',
            'hotel.guests.view', 'hotel.guests.create', 'hotel.guests.edit', 'hotel.guests.delete',
            'hotel.reservations.view', 'hotel.reservations.create', 'hotel.reservations.edit', 'hotel.reservations.delete',
            'hotel.walk-in.create', 'hotel.checkout.manage',
            'hotel.calendar.view',
            'hotel.housekeeping.view', 'hotel.housekeeping.manage',
            'hotel.maintenance.view', 'hotel.maintenance.create', 'hotel.maintenance.edit',
            'hotel.staff.view', 'hotel.staff.create', 'hotel.staff.edit',
            'hotel.reports.view',
            'hotel.rates.view', 'hotel.rates.create', 'hotel.rates.edit',
            'hotel.packages.view', 'hotel.packages.create', 'hotel.packages.edit',
            
            // Salão de Beleza
            'salon.dashboard', 'salon.settings.view', 'salon.settings.edit',
            'salon.appointments.view', 'salon.appointments.create', 'salon.appointments.edit', 'salon.appointments.delete',
            'salon.services.view', 'salon.services.create', 'salon.services.edit', 'salon.services.delete',
            'salon.categories.view', 'salon.categories.create', 'salon.categories.edit',
            'salon.professionals.view', 'salon.professionals.create', 'salon.professionals.edit', 'salon.professionals.delete',
            'salon.clients.view', 'salon.clients.create', 'salon.clients.edit', 'salon.clients.delete',
            'salon.products.view', 'salon.products.create', 'salon.products.edit',
            'salon.pos.access', 'salon.pos.sell',
            'salon.reports.view',
            
            // Configurações
            'settings.view', 'settings.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
