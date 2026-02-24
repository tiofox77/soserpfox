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
        $createPermission('invoicing.clients.view', 'Ver Clientes');
        $createPermission('invoicing.clients.create', 'Criar Clientes');
        $createPermission('invoicing.clients.edit', 'Editar Clientes');
        $createPermission('invoicing.clients.delete', 'Eliminar Clientes');
        $createPermission('invoicing.clients.export', 'Exportar Clientes');

        // Fornecedores
        $createPermission('invoicing.suppliers.view', 'Ver Fornecedores');
        $createPermission('invoicing.suppliers.create', 'Criar Fornecedores');
        $createPermission('invoicing.suppliers.edit', 'Editar Fornecedores');
        $createPermission('invoicing.suppliers.delete', 'Eliminar Fornecedores');

        // Produtos
        $createPermission('invoicing.products.view', 'Ver Produtos');
        $createPermission('invoicing.products.create', 'Criar Produtos');
        $createPermission('invoicing.products.edit', 'Editar Produtos');
        $createPermission('invoicing.products.delete', 'Eliminar Produtos');
        $createPermission('invoicing.products.import', 'Importar Produtos');

        // Categorias
        $createPermission('invoicing.categories.view', 'Ver Categorias');
        $createPermission('invoicing.categories.create', 'Criar Categorias');
        $createPermission('invoicing.categories.edit', 'Editar Categorias');
        $createPermission('invoicing.categories.delete', 'Eliminar Categorias');

        // Marcas
        $createPermission('invoicing.brands.view', 'Ver Marcas');
        $createPermission('invoicing.brands.create', 'Criar Marcas');
        $createPermission('invoicing.brands.edit', 'Editar Marcas');
        $createPermission('invoicing.brands.delete', 'Eliminar Marcas');

        // Faturas de Venda
        $createPermission('invoicing.sales.invoices.view', 'Ver Faturas de Venda');
        $createPermission('invoicing.sales.invoices.create', 'Criar Faturas de Venda');
        $createPermission('invoicing.sales.invoices.edit', 'Editar Faturas de Venda');
        $createPermission('invoicing.sales.invoices.delete', 'Eliminar Faturas de Venda');
        $createPermission('invoicing.sales.invoices.pdf', 'Gerar PDF Faturas de Venda');
        $createPermission('invoicing.sales.invoices.cancel', 'Cancelar Faturas de Venda');

        // Proformas de Venda
        $createPermission('invoicing.sales.proformas.view', 'Ver Proformas de Venda');
        $createPermission('invoicing.sales.proformas.create', 'Criar Proformas de Venda');
        $createPermission('invoicing.sales.proformas.edit', 'Editar Proformas de Venda');
        $createPermission('invoicing.sales.proformas.delete', 'Eliminar Proformas de Venda');
        $createPermission('invoicing.sales.proformas.convert', 'Converter Proformas em Faturas');

        // Faturas de Compra
        $createPermission('invoicing.purchases.invoices.view', 'Ver Faturas de Compra');
        $createPermission('invoicing.purchases.invoices.create', 'Criar Faturas de Compra');
        $createPermission('invoicing.purchases.invoices.edit', 'Editar Faturas de Compra');
        $createPermission('invoicing.purchases.invoices.delete', 'Eliminar Faturas de Compra');

        // Proformas de Compra
        $createPermission('invoicing.purchases.proformas.view', 'Ver Proformas de Compra');
        $createPermission('invoicing.purchases.proformas.create', 'Criar Proformas de Compra');
        $createPermission('invoicing.purchases.proformas.edit', 'Editar Proformas de Compra');
        $createPermission('invoicing.purchases.proformas.delete', 'Eliminar Proformas de Compra');

        // Recibos
        $createPermission('invoicing.receipts.view', 'Ver Recibos');
        $createPermission('invoicing.receipts.create', 'Criar Recibos');
        $createPermission('invoicing.receipts.edit', 'Editar Recibos');
        $createPermission('invoicing.receipts.delete', 'Eliminar Recibos');
        $createPermission('invoicing.receipts.cancel', 'Cancelar Recibos');

        // Notas de Crédito
        $createPermission('invoicing.credit-notes.view', 'Ver Notas de Crédito');
        $createPermission('invoicing.credit-notes.create', 'Criar Notas de Crédito');
        $createPermission('invoicing.credit-notes.edit', 'Editar Notas de Crédito');
        $createPermission('invoicing.credit-notes.delete', 'Eliminar Notas de Crédito');

        // Notas de Débito
        $createPermission('invoicing.debit-notes.view', 'Ver Notas de Débito');
        $createPermission('invoicing.debit-notes.create', 'Criar Notas de Débito');
        $createPermission('invoicing.debit-notes.edit', 'Editar Notas de Débito');
        $createPermission('invoicing.debit-notes.delete', 'Eliminar Notas de Débito');

        // Adiantamentos
        $createPermission('invoicing.advances.view', 'Ver Adiantamentos');
        $createPermission('invoicing.advances.create', 'Criar Adiantamentos');
        $createPermission('invoicing.advances.edit', 'Editar Adiantamentos');
        $createPermission('invoicing.advances.delete', 'Eliminar Adiantamentos');

        // Importações
        $createPermission('invoicing.imports.view', 'Ver Importações');
        $createPermission('invoicing.imports.create', 'Criar Importações');
        $createPermission('invoicing.imports.edit', 'Editar Importações');
        $createPermission('invoicing.imports.delete', 'Eliminar Importações');

        // POS - Ponto de Venda
        $createPermission('invoicing.pos.access', 'Acessar POS');
        $createPermission('invoicing.pos.sell', 'Realizar Vendas no POS');
        $createPermission('invoicing.pos.refund', 'Fazer Devoluções no POS');
        $createPermission('invoicing.pos.reports', 'Ver Relatórios POS');
        $createPermission('invoicing.pos.settings', 'Configurar POS');

        // Configurações
        $createPermission('invoicing.settings.view', 'Ver Configurações de Faturação');
        $createPermission('invoicing.settings.edit', 'Editar Configurações de Faturação');
        
        // AGT Angola
        $createPermission('invoicing.agt.view', 'Ver Configurações AGT Angola');
        $createPermission('invoicing.agt.edit', 'Editar Configurações AGT Angola');

        // Armazéns
        $createPermission('invoicing.warehouses.view', 'Ver Armazéns');
        $createPermission('invoicing.warehouses.create', 'Criar Armazéns');
        $createPermission('invoicing.warehouses.edit', 'Editar Armazéns');
        $createPermission('invoicing.warehouses.delete', 'Eliminar Armazéns');

        // Gestão de Stock
        $createPermission('invoicing.stock.view', 'Ver Gestão de Stock');
        $createPermission('invoicing.stock.edit', 'Editar Stock');

        // Transferências de Armazém
        $createPermission('invoicing.warehouse-transfer.view', 'Ver Transferências de Armazém');
        $createPermission('invoicing.warehouse-transfer.create', 'Criar Transferências de Armazém');

        // Transferências Inter-Empresa
        $createPermission('invoicing.inter-company-transfer.view', 'Ver Transferências Inter-Empresa');
        $createPermission('invoicing.inter-company-transfer.create', 'Criar Transferências Inter-Empresa');

        // Impostos (IVA)
        $createPermission('invoicing.taxes.view', 'Ver Impostos (IVA)');
        $createPermission('invoicing.taxes.edit', 'Editar Impostos');

        // Séries de Documentos
        $createPermission('invoicing.series.view', 'Ver Séries de Documentos');
        $createPermission('invoicing.series.edit', 'Editar Séries');

        // SAFT-AO
        $createPermission('invoicing.saft.view', 'Ver Gerador SAFT-AO');
        $createPermission('invoicing.saft.generate', 'Gerar Ficheiro SAFT');

        // ==========================================
        // MÓDULO DE TESOURARIA - PERMISSÕES
        // ==========================================

        // Contas Bancárias
        $createPermission('treasury.accounts.view', 'Ver Contas Bancárias');
        $createPermission('treasury.accounts.create', 'Criar Contas Bancárias');
        $createPermission('treasury.accounts.edit', 'Editar Contas Bancárias');
        $createPermission('treasury.accounts.delete', 'Eliminar Contas Bancárias');

        // Movimentos
        $createPermission('treasury.transactions.view', 'Ver Movimentos');
        $createPermission('treasury.transactions.create', 'Criar Movimentos');
        $createPermission('treasury.transactions.edit', 'Editar Movimentos');
        $createPermission('treasury.transactions.delete', 'Eliminar Movimentos');

        // Transferências
        $createPermission('treasury.transfers.view', 'Ver Transferências');
        $createPermission('treasury.transfers.create', 'Criar Transferências');

        // Relatórios
        $createPermission('treasury.reports.view', 'Ver Relatórios de Tesouraria');

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
