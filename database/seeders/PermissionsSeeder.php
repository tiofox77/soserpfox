<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Helper function para criar ou atualizar permissões
        $createPermission = function($name, $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'guard_name' => 'web']
            );
        };

        // ==========================================
        // MÓDULO DE FATURAÇÃO - PERMISSÕES
        // ==========================================

        // Dashboard
        $createPermission('invoicing.dashboard.view', 'Ver Dashboard de Faturação');

        // Clientes
        Permission::create(['name' => 'invoicing.clients.view', 'description' => 'Ver Clientes']);
        Permission::create(['name' => 'invoicing.clients.create', 'description' => 'Criar Clientes']);
        Permission::create(['name' => 'invoicing.clients.edit', 'description' => 'Editar Clientes']);
        Permission::create(['name' => 'invoicing.clients.delete', 'description' => 'Eliminar Clientes']);
        Permission::create(['name' => 'invoicing.clients.export', 'description' => 'Exportar Clientes']);

        // Fornecedores
        Permission::create(['name' => 'invoicing.suppliers.view', 'description' => 'Ver Fornecedores']);
        Permission::create(['name' => 'invoicing.suppliers.create', 'description' => 'Criar Fornecedores']);
        Permission::create(['name' => 'invoicing.suppliers.edit', 'description' => 'Editar Fornecedores']);
        Permission::create(['name' => 'invoicing.suppliers.delete', 'description' => 'Eliminar Fornecedores']);

        // Produtos
        Permission::create(['name' => 'invoicing.products.view', 'description' => 'Ver Produtos']);
        Permission::create(['name' => 'invoicing.products.create', 'description' => 'Criar Produtos']);
        Permission::create(['name' => 'invoicing.products.edit', 'description' => 'Editar Produtos']);
        Permission::create(['name' => 'invoicing.products.delete', 'description' => 'Eliminar Produtos']);
        Permission::create(['name' => 'invoicing.products.import', 'description' => 'Importar Produtos']);

        // Categorias
        Permission::create(['name' => 'invoicing.categories.view', 'description' => 'Ver Categorias']);
        Permission::create(['name' => 'invoicing.categories.create', 'description' => 'Criar Categorias']);
        Permission::create(['name' => 'invoicing.categories.edit', 'description' => 'Editar Categorias']);
        Permission::create(['name' => 'invoicing.categories.delete', 'description' => 'Eliminar Categorias']);

        // Marcas
        Permission::create(['name' => 'invoicing.brands.view', 'description' => 'Ver Marcas']);
        Permission::create(['name' => 'invoicing.brands.create', 'description' => 'Criar Marcas']);
        Permission::create(['name' => 'invoicing.brands.edit', 'description' => 'Editar Marcas']);
        Permission::create(['name' => 'invoicing.brands.delete', 'description' => 'Eliminar Marcas']);

        // Faturas de Venda
        Permission::create(['name' => 'invoicing.sales.invoices.view', 'description' => 'Ver Faturas de Venda']);
        Permission::create(['name' => 'invoicing.sales.invoices.create', 'description' => 'Criar Faturas de Venda']);
        Permission::create(['name' => 'invoicing.sales.invoices.edit', 'description' => 'Editar Faturas de Venda']);
        Permission::create(['name' => 'invoicing.sales.invoices.delete', 'description' => 'Eliminar Faturas de Venda']);
        Permission::create(['name' => 'invoicing.sales.invoices.pdf', 'description' => 'Gerar PDF Faturas de Venda']);
        Permission::create(['name' => 'invoicing.sales.invoices.cancel', 'description' => 'Cancelar Faturas de Venda']);

        // Proformas de Venda
        Permission::create(['name' => 'invoicing.sales.proformas.view', 'description' => 'Ver Proformas de Venda']);
        Permission::create(['name' => 'invoicing.sales.proformas.create', 'description' => 'Criar Proformas de Venda']);
        Permission::create(['name' => 'invoicing.sales.proformas.edit', 'description' => 'Editar Proformas de Venda']);
        Permission::create(['name' => 'invoicing.sales.proformas.delete', 'description' => 'Eliminar Proformas de Venda']);
        Permission::create(['name' => 'invoicing.sales.proformas.convert', 'description' => 'Converter Proformas em Faturas']);

        // Faturas de Compra
        Permission::create(['name' => 'invoicing.purchases.invoices.view', 'description' => 'Ver Faturas de Compra']);
        Permission::create(['name' => 'invoicing.purchases.invoices.create', 'description' => 'Criar Faturas de Compra']);
        Permission::create(['name' => 'invoicing.purchases.invoices.edit', 'description' => 'Editar Faturas de Compra']);
        Permission::create(['name' => 'invoicing.purchases.invoices.delete', 'description' => 'Eliminar Faturas de Compra']);

        // Proformas de Compra
        Permission::create(['name' => 'invoicing.purchases.proformas.view', 'description' => 'Ver Proformas de Compra']);
        Permission::create(['name' => 'invoicing.purchases.proformas.create', 'description' => 'Criar Proformas de Compra']);
        Permission::create(['name' => 'invoicing.purchases.proformas.edit', 'description' => 'Editar Proformas de Compra']);
        Permission::create(['name' => 'invoicing.purchases.proformas.delete', 'description' => 'Eliminar Proformas de Compra']);

        // Recibos
        Permission::create(['name' => 'invoicing.receipts.view', 'description' => 'Ver Recibos']);
        Permission::create(['name' => 'invoicing.receipts.create', 'description' => 'Criar Recibos']);
        Permission::create(['name' => 'invoicing.receipts.edit', 'description' => 'Editar Recibos']);
        Permission::create(['name' => 'invoicing.receipts.delete', 'description' => 'Eliminar Recibos']);
        Permission::create(['name' => 'invoicing.receipts.cancel', 'description' => 'Cancelar Recibos']);

        // Notas de Crédito
        Permission::create(['name' => 'invoicing.credit-notes.view', 'description' => 'Ver Notas de Crédito']);
        Permission::create(['name' => 'invoicing.credit-notes.create', 'description' => 'Criar Notas de Crédito']);
        Permission::create(['name' => 'invoicing.credit-notes.edit', 'description' => 'Editar Notas de Crédito']);
        Permission::create(['name' => 'invoicing.credit-notes.delete', 'description' => 'Eliminar Notas de Crédito']);

        // Notas de Débito
        Permission::create(['name' => 'invoicing.debit-notes.view', 'description' => 'Ver Notas de Débito']);
        Permission::create(['name' => 'invoicing.debit-notes.create', 'description' => 'Criar Notas de Débito']);
        Permission::create(['name' => 'invoicing.debit-notes.edit', 'description' => 'Editar Notas de Débito']);
        Permission::create(['name' => 'invoicing.debit-notes.delete', 'description' => 'Eliminar Notas de Débito']);

        // Adiantamentos
        Permission::create(['name' => 'invoicing.advances.view', 'description' => 'Ver Adiantamentos']);
        Permission::create(['name' => 'invoicing.advances.create', 'description' => 'Criar Adiantamentos']);
        Permission::create(['name' => 'invoicing.advances.edit', 'description' => 'Editar Adiantamentos']);
        Permission::create(['name' => 'invoicing.advances.delete', 'description' => 'Eliminar Adiantamentos']);

        // Importações
        Permission::create(['name' => 'invoicing.imports.view', 'description' => 'Ver Importações']);
        Permission::create(['name' => 'invoicing.imports.create', 'description' => 'Criar Importações']);
        Permission::create(['name' => 'invoicing.imports.edit', 'description' => 'Editar Importações']);
        Permission::create(['name' => 'invoicing.imports.delete', 'description' => 'Eliminar Importações']);

        // POS - Ponto de Venda
        Permission::create(['name' => 'invoicing.pos.access', 'description' => 'Acessar POS']);
        Permission::create(['name' => 'invoicing.pos.sell', 'description' => 'Realizar Vendas no POS']);
        Permission::create(['name' => 'invoicing.pos.refund', 'description' => 'Fazer Devoluções no POS']);
        Permission::create(['name' => 'invoicing.pos.reports', 'description' => 'Ver Relatórios POS']);
        Permission::create(['name' => 'invoicing.pos.settings', 'description' => 'Configurar POS']);

        // Configurações
        Permission::create(['name' => 'invoicing.settings.view', 'description' => 'Ver Configurações de Faturação']);
        Permission::create(['name' => 'invoicing.settings.edit', 'description' => 'Editar Configurações de Faturação']);
        
        // AGT Angola
        Permission::create(['name' => 'invoicing.agt.view', 'description' => 'Ver Configurações AGT Angola']);
        Permission::create(['name' => 'invoicing.agt.edit', 'description' => 'Editar Configurações AGT Angola']);

        // Armazéns
        Permission::create(['name' => 'invoicing.warehouses.view', 'description' => 'Ver Armazéns']);
        Permission::create(['name' => 'invoicing.warehouses.create', 'description' => 'Criar Armazéns']);
        Permission::create(['name' => 'invoicing.warehouses.edit', 'description' => 'Editar Armazéns']);
        Permission::create(['name' => 'invoicing.warehouses.delete', 'description' => 'Eliminar Armazéns']);

        // Gestão de Stock
        Permission::create(['name' => 'invoicing.stock.view', 'description' => 'Ver Gestão de Stock']);
        Permission::create(['name' => 'invoicing.stock.edit', 'description' => 'Editar Stock']);

        // Transferências de Armazém
        Permission::create(['name' => 'invoicing.warehouse-transfer.view', 'description' => 'Ver Transferências de Armazém']);
        Permission::create(['name' => 'invoicing.warehouse-transfer.create', 'description' => 'Criar Transferências de Armazém']);

        // Transferências Inter-Empresa
        Permission::create(['name' => 'invoicing.inter-company-transfer.view', 'description' => 'Ver Transferências Inter-Empresa']);
        Permission::create(['name' => 'invoicing.inter-company-transfer.create', 'description' => 'Criar Transferências Inter-Empresa']);

        // Impostos (IVA)
        Permission::create(['name' => 'invoicing.taxes.view', 'description' => 'Ver Impostos (IVA)']);
        Permission::create(['name' => 'invoicing.taxes.edit', 'description' => 'Editar Impostos']);

        // Séries de Documentos
        Permission::create(['name' => 'invoicing.series.view', 'description' => 'Ver Séries de Documentos']);
        Permission::create(['name' => 'invoicing.series.edit', 'description' => 'Editar Séries']);

        // SAFT-AO
        Permission::create(['name' => 'invoicing.saft.view', 'description' => 'Ver Gerador SAFT-AO']);
        Permission::create(['name' => 'invoicing.saft.generate', 'description' => 'Gerar Ficheiro SAFT']);

        // ==========================================
        // MÓDULO DE TESOURARIA - PERMISSÕES
        // ==========================================

        // Contas Bancárias
        Permission::create(['name' => 'treasury.accounts.view', 'description' => 'Ver Contas Bancárias']);
        Permission::create(['name' => 'treasury.accounts.create', 'description' => 'Criar Contas Bancárias']);
        Permission::create(['name' => 'treasury.accounts.edit', 'description' => 'Editar Contas Bancárias']);
        Permission::create(['name' => 'treasury.accounts.delete', 'description' => 'Eliminar Contas Bancárias']);

        // Movimentos
        Permission::create(['name' => 'treasury.transactions.view', 'description' => 'Ver Movimentos']);
        Permission::create(['name' => 'treasury.transactions.create', 'description' => 'Criar Movimentos']);
        Permission::create(['name' => 'treasury.transactions.edit', 'description' => 'Editar Movimentos']);
        Permission::create(['name' => 'treasury.transactions.delete', 'description' => 'Eliminar Movimentos']);

        // Transferências
        Permission::create(['name' => 'treasury.transfers.view', 'description' => 'Ver Transferências']);
        Permission::create(['name' => 'treasury.transfers.create', 'description' => 'Criar Transferências']);

        // Relatórios
        Permission::create(['name' => 'treasury.reports.view', 'description' => 'Ver Relatórios de Tesouraria']);

        // ==========================================
        // ROLES (PAPÉIS) PREDEFINIDOS
        // ==========================================

        // Super Admin - Todas as permissões (verificar se já existe)
        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            ['description' => 'Acesso total ao sistema']
        );
        $superAdmin->syncPermissions(Permission::all());

        // Administrador de Faturação
        $invoicingAdmin = Role::firstOrCreate(
            ['name' => 'Administrador Faturação'],
            ['description' => 'Gestão completa do módulo de faturação']
        );
        $invoicingAdmin->givePermissionTo([
            // Dashboard
            'invoicing.dashboard.view',
            // Clientes
            'invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit', 'invoicing.clients.delete', 'invoicing.clients.export',
            // Fornecedores
            'invoicing.suppliers.view', 'invoicing.suppliers.create', 'invoicing.suppliers.edit', 'invoicing.suppliers.delete',
            // Produtos
            'invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit', 'invoicing.products.delete', 'invoicing.products.import',
            // Categorias e Marcas
            'invoicing.categories.view', 'invoicing.categories.create', 'invoicing.categories.edit', 'invoicing.categories.delete',
            'invoicing.brands.view', 'invoicing.brands.create', 'invoicing.brands.edit', 'invoicing.brands.delete',
            // Faturas
            'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.edit', 'invoicing.sales.invoices.delete', 'invoicing.sales.invoices.pdf', 'invoicing.sales.invoices.cancel',
            'invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create', 'invoicing.purchases.invoices.edit', 'invoicing.purchases.invoices.delete',
            // Proformas
            'invoicing.sales.proformas.view', 'invoicing.sales.proformas.create', 'invoicing.sales.proformas.edit', 'invoicing.sales.proformas.delete', 'invoicing.sales.proformas.convert',
            'invoicing.purchases.proformas.view', 'invoicing.purchases.proformas.create', 'invoicing.purchases.proformas.edit', 'invoicing.purchases.proformas.delete',
            // Recibos
            'invoicing.receipts.view', 'invoicing.receipts.create', 'invoicing.receipts.edit', 'invoicing.receipts.delete', 'invoicing.receipts.cancel',
            // Notas
            'invoicing.credit-notes.view', 'invoicing.credit-notes.create', 'invoicing.credit-notes.edit', 'invoicing.credit-notes.delete',
            'invoicing.debit-notes.view', 'invoicing.debit-notes.create', 'invoicing.debit-notes.edit', 'invoicing.debit-notes.delete',
            // Adiantamentos
            'invoicing.advances.view', 'invoicing.advances.create', 'invoicing.advances.edit', 'invoicing.advances.delete',
            // Importações
            'invoicing.imports.view', 'invoicing.imports.create', 'invoicing.imports.edit', 'invoicing.imports.delete',
            // POS
            'invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.pos.refund', 'invoicing.pos.reports', 'invoicing.pos.settings',
            // Configurações
            'invoicing.settings.view', 'invoicing.settings.edit',
            // AGT Angola
            'invoicing.agt.view', 'invoicing.agt.edit',
            // Armazéns e Stock
            'invoicing.warehouses.view', 'invoicing.warehouses.create', 'invoicing.warehouses.edit', 'invoicing.warehouses.delete',
            'invoicing.stock.view', 'invoicing.stock.edit',
            // Transferências
            'invoicing.warehouse-transfer.view', 'invoicing.warehouse-transfer.create',
            'invoicing.inter-company-transfer.view', 'invoicing.inter-company-transfer.create',
            // Impostos e Séries
            'invoicing.taxes.view', 'invoicing.taxes.edit',
            'invoicing.series.view', 'invoicing.series.edit',
            // SAFT
            'invoicing.saft.view', 'invoicing.saft.generate',
        ]);

        // Vendedor
        $seller = Role::firstOrCreate(
            ['name' => 'Vendedor'],
            ['description' => 'Vendas e atendimento ao cliente']
        );
        $seller->givePermissionTo([
            'invoicing.dashboard.view',
            'invoicing.clients.view', 'invoicing.clients.create', 'invoicing.clients.edit',
            'invoicing.products.view',
            'invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.pdf',
            'invoicing.sales.proformas.view', 'invoicing.sales.proformas.create', 'invoicing.sales.proformas.convert',
            'invoicing.receipts.view', 'invoicing.receipts.create',
            'invoicing.pos.access', 'invoicing.pos.sell',
        ]);

        // Caixa/Tesoureiro
        $cashier = Role::firstOrCreate(
            ['name' => 'Caixa'],
            ['description' => 'Gestão de pagamentos e recebimentos']
        );
        $cashier->givePermissionTo([
            'invoicing.dashboard.view',
            'invoicing.clients.view',
            'invoicing.sales.invoices.view',
            'invoicing.receipts.view', 'invoicing.receipts.create', 'invoicing.receipts.edit',
            'invoicing.pos.access', 'invoicing.pos.sell',
            'treasury.accounts.view',
            'treasury.transactions.view', 'treasury.transactions.create',
            'treasury.transfers.view', 'treasury.transfers.create',
        ]);

        // Contabilista
        $accountant = Role::firstOrCreate(
            ['name' => 'Contabilista'],
            ['description' => 'Visualização de documentos fiscais']
        );
        $accountant->givePermissionTo([
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

        // Operador de Stock
        $stockOperator = Role::firstOrCreate(
            ['name' => 'Operador Stock'],
            ['description' => 'Gestão de produtos e stock']
        );
        $stockOperator->givePermissionTo([
            'invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit', 'invoicing.products.import',
            'invoicing.categories.view', 'invoicing.categories.create', 'invoicing.categories.edit',
            'invoicing.brands.view', 'invoicing.brands.create', 'invoicing.brands.edit',
            'invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create',
            'invoicing.imports.view', 'invoicing.imports.create', 'invoicing.imports.edit',
            // Armazéns e Stock
            'invoicing.warehouses.view', 'invoicing.warehouses.create', 'invoicing.warehouses.edit',
            'invoicing.stock.view', 'invoicing.stock.edit',
            // Transferências
            'invoicing.warehouse-transfer.view', 'invoicing.warehouse-transfer.create',
        ]);

        $this->command->info('✅ Permissões e Roles criados com sucesso!');
    }
}
