<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UpdatePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Atualizando roles com permissões...');

        // Atualizar Super Admin com todas as permissões
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
            $this->command->info('✓ Super Admin atualizado');
        }

        // Atualizar Administrador de Faturação
        $invoicingAdmin = Role::where('name', 'Administrador Faturação')->first();
        if ($invoicingAdmin) {
            $invoicingAdmin->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit', 'invoicing.clients.delete', 'invoicing.clients.export',
                'invoicing.suppliers.view', 'invoicing.suppliers.create', 'invoicing.suppliers.edit', 'invoicing.suppliers.delete',
                'invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit', 'invoicing.products.delete', 'invoicing.products.import',
                'invoicing.categories.view', 'invoicing.categories.create', 'invoicing.categories.edit', 'invoicing.categories.delete',
                'invoicing.brands.view', 'invoicing.brands.create', 'invoicing.brands.edit', 'invoicing.brands.delete',
                'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.edit', 'invoicing.sales.invoices.delete', 'invoicing.sales.invoices.pdf', 'invoicing.sales.invoices.cancel',
                'invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create', 'invoicing.purchases.invoices.edit', 'invoicing.purchases.invoices.delete',
                'invoicing.sales.proformas.view', 'invoicing.sales.proformas.create', 'invoicing.sales.proformas.edit', 'invoicing.sales.proformas.delete', 'invoicing.sales.proformas.convert',
                'invoicing.purchases.proformas.view', 'invoicing.purchases.proformas.create', 'invoicing.purchases.proformas.edit', 'invoicing.purchases.proformas.delete',
                'invoicing.receipts.view', 'invoicing.receipts.create', 'invoicing.receipts.edit', 'invoicing.receipts.delete', 'invoicing.receipts.cancel',
                'invoicing.credit-notes.view', 'invoicing.credit-notes.create', 'invoicing.credit-notes.edit', 'invoicing.credit-notes.delete',
                'invoicing.debit-notes.view', 'invoicing.debit-notes.create', 'invoicing.debit-notes.edit', 'invoicing.debit-notes.delete',
                'invoicing.advances.view', 'invoicing.advances.create', 'invoicing.advances.edit', 'invoicing.advances.delete',
                'invoicing.imports.view', 'invoicing.imports.create', 'invoicing.imports.edit', 'invoicing.imports.delete',
                'invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.pos.refund', 'invoicing.pos.reports', 'invoicing.pos.settings',
                'invoicing.settings.view', 'invoicing.settings.edit',
            ]);
            $this->command->info('✓ Administrador Faturação atualizado');
        }

        // Atualizar Vendedor
        $seller = Role::where('name', 'Vendedor')->first();
        if ($seller) {
            $seller->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit',
                'invoicing.products.view',
                'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.pdf',
                'invoicing.sales.proformas.view', 'invoicing.sales.proformas.create', 'invoicing.sales.proformas.convert',
                'invoicing.receipts.view', 'invoicing.receipts.create',
                'invoicing.pos.access', 'invoicing.pos.sell',
            ]);
            $this->command->info('✓ Vendedor atualizado');
        }

        // Atualizar Caixa
        $cashier = Role::where('name', 'Caixa')->first();
        if ($cashier) {
            $cashier->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view',
                'invoicing.sales.invoices.view',
                'invoicing.receipts.view', 'invoicing.receipts.create', 'invoicing.receipts.edit',
                'invoicing.pos.access', 'invoicing.pos.sell',
                'treasury.accounts.view',
                'treasury.transactions.view', 'treasury.transactions.create',
                'treasury.transfers.view', 'treasury.transfers.create',
            ]);
            $this->command->info('✓ Caixa atualizado');
        }

        // Atualizar Contabilista
        $accountant = Role::where('name', 'Contabilista')->first();
        if ($accountant) {
            $accountant->syncPermissions([
                'invoicing.dashboard.view',
                'invoicing.clients.view',
                'invoicing.suppliers.view',
                'invoicing.sales.invoices.view', 'invoicing.sales.invoices.pdf',
                'invoicing.purchases.invoices.view',
                'invoicing.receipts.view',
                'invoicing.credit-notes.view',
                'invoicing.debit-notes.view',
                'treasury.accounts.view',
                'treasury.transactions.view',
                'treasury.reports.view',
            ]);
            $this->command->info('✓ Contabilista atualizado');
        }

        // Atualizar Operador Stock
        $stockOperator = Role::where('name', 'Operador Stock')->first();
        if ($stockOperator) {
            $stockOperator->syncPermissions([
                'invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit', 'invoicing.products.import',
                'invoicing.categories.view', 'invoicing.categories.create', 'invoicing.categories.edit',
                'invoicing.brands.view', 'invoicing.brands.create', 'invoicing.brands.edit',
                'invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create',
                'invoicing.imports.view', 'invoicing.imports.create', 'invoicing.imports.edit',
            ]);
            $this->command->info('✓ Operador Stock atualizado');
        }

        $this->command->info('✅ Roles atualizados com sucesso!');
    }
}
