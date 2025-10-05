<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UpdateOldRolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🔧 Atualizando roles antigos com novas permissões...');

        // ADMIN - Equivalente a Administrador Faturação
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $this->command->info('📌 Atualizando role: Admin');
            $admin->update(['description' => 'Administrador com acesso completo']);
            
            $admin->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit', 'invoicing.clients.delete',
                'invoicing.suppliers.view', 'invoicing.suppliers.create', 'invoicing.suppliers.edit', 'invoicing.suppliers.delete',
                'invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit', 'invoicing.products.delete',
                'invoicing.categories.view', 'invoicing.categories.create', 'invoicing.categories.edit', 'invoicing.categories.delete',
                'invoicing.brands.view', 'invoicing.brands.create', 'invoicing.brands.edit', 'invoicing.brands.delete',
                'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.edit', 'invoicing.sales.invoices.delete', 'invoicing.sales.invoices.pdf',
                'invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create', 'invoicing.purchases.invoices.edit',
                'invoicing.sales.proformas.view', 'invoicing.sales.proformas.create', 'invoicing.sales.proformas.edit',
                'invoicing.receipts.view', 'invoicing.receipts.create', 'invoicing.receipts.edit',
                'invoicing.credit-notes.view', 'invoicing.credit-notes.create',
                'invoicing.debit-notes.view', 'invoicing.debit-notes.create',
                'invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.pos.reports',
                'invoicing.warehouses.view', 'invoicing.warehouses.create', 'invoicing.warehouses.edit',
                'invoicing.stock.view', 'invoicing.stock.edit',
                'invoicing.taxes.view', 'invoicing.taxes.edit',
                'invoicing.series.view', 'invoicing.series.edit',
                'invoicing.settings.view', 'invoicing.settings.edit',
                'treasury.accounts.view', 'treasury.accounts.create', 'treasury.accounts.edit',
                'treasury.transactions.view', 'treasury.transactions.create',
                'treasury.payment-methods.view', 'treasury.banks.view', 'treasury.cash-registers.view',
            ]);
            $this->command->info('✅ Admin atualizado com ' . $admin->permissions->count() . ' permissões');
        }

        // GESTOR - Gestão operacional (sem delete)
        $gestor = Role::where('name', 'Gestor')->first();
        if ($gestor) {
            $this->command->info('📌 Atualizando role: Gestor');
            $gestor->update(['description' => 'Gestor operacional']);
            
            $gestor->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit',
                'invoicing.suppliers.view',
                'invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit',
                'invoicing.categories.view', 'invoicing.brands.view',
                'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.edit', 'invoicing.sales.invoices.pdf',
                'invoicing.purchases.invoices.view',
                'invoicing.sales.proformas.view', 'invoicing.sales.proformas.create',
                'invoicing.receipts.view', 'invoicing.receipts.create',
                'invoicing.pos.access', 'invoicing.pos.sell',
                'invoicing.warehouses.view', 'invoicing.stock.view',
                'treasury.accounts.view', 'treasury.transactions.view',
                'treasury.payment-methods.view', 'treasury.banks.view', 'treasury.cash-registers.view',
            ]);
            $this->command->info('✅ Gestor atualizado com ' . $gestor->permissions->count() . ' permissões');
        }

        // UTILIZADOR - Apenas visualização
        $utilizador = Role::where('name', 'Utilizador')->first();
        if ($utilizador) {
            $this->command->info('📌 Atualizando role: Utilizador');
            $utilizador->update(['description' => 'Utilizador com acesso apenas de visualização']);
            
            $utilizador->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view',
                'invoicing.suppliers.view',
                'invoicing.products.view',
                'invoicing.categories.view',
                'invoicing.brands.view',
                'invoicing.sales.invoices.view', 'invoicing.sales.invoices.pdf',
                'invoicing.purchases.invoices.view',
                'invoicing.receipts.view',
                'treasury.accounts.view',
                'treasury.transactions.view',
            ]);
            $this->command->info('✅ Utilizador atualizado com ' . $utilizador->permissions->count() . ' permissões');
        }

        $this->command->info('✅ Todos os roles atualizados!');
    }
}
