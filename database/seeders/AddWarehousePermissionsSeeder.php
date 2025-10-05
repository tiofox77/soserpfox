<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddWarehousePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🔄 Criando novas permissões...');

        // Criar permissões (ou pular se já existirem)
        $permissions = [
            // Armazéns
            ['name' => 'invoicing.warehouses.view', 'description' => 'Ver Armazéns'],
            ['name' => 'invoicing.warehouses.create', 'description' => 'Criar Armazéns'],
            ['name' => 'invoicing.warehouses.edit', 'description' => 'Editar Armazéns'],
            ['name' => 'invoicing.warehouses.delete', 'description' => 'Eliminar Armazéns'],
            // Gestão de Stock
            ['name' => 'invoicing.stock.view', 'description' => 'Ver Gestão de Stock'],
            ['name' => 'invoicing.stock.edit', 'description' => 'Editar Stock'],
            // Transferências de Armazém
            ['name' => 'invoicing.warehouse-transfer.view', 'description' => 'Ver Transferências de Armazém'],
            ['name' => 'invoicing.warehouse-transfer.create', 'description' => 'Criar Transferências de Armazém'],
            // Transferências Inter-Empresa
            ['name' => 'invoicing.inter-company-transfer.view', 'description' => 'Ver Transferências Inter-Empresa'],
            ['name' => 'invoicing.inter-company-transfer.create', 'description' => 'Criar Transferências Inter-Empresa'],
            // Impostos (IVA)
            ['name' => 'invoicing.taxes.view', 'description' => 'Ver Impostos (IVA)'],
            ['name' => 'invoicing.taxes.edit', 'description' => 'Editar Impostos'],
            // Séries de Documentos
            ['name' => 'invoicing.series.view', 'description' => 'Ver Séries de Documentos'],
            ['name' => 'invoicing.series.edit', 'description' => 'Editar Séries'],
            // SAFT-AO
            ['name' => 'invoicing.saft.view', 'description' => 'Ver Gerador SAFT-AO'],
            ['name' => 'invoicing.saft.generate', 'description' => 'Gerar Ficheiro SAFT'],
            // Tesouraria - Métodos de Pagamento
            ['name' => 'treasury.payment-methods.view', 'description' => 'Ver Métodos de Pagamento'],
            ['name' => 'treasury.payment-methods.create', 'description' => 'Criar Métodos de Pagamento'],
            ['name' => 'treasury.payment-methods.edit', 'description' => 'Editar Métodos de Pagamento'],
            ['name' => 'treasury.payment-methods.delete', 'description' => 'Eliminar Métodos de Pagamento'],
            // Tesouraria - Bancos
            ['name' => 'treasury.banks.view', 'description' => 'Ver Bancos'],
            ['name' => 'treasury.banks.create', 'description' => 'Criar Bancos'],
            ['name' => 'treasury.banks.edit', 'description' => 'Editar Bancos'],
            ['name' => 'treasury.banks.delete', 'description' => 'Eliminar Bancos'],
            // Tesouraria - Caixas
            ['name' => 'treasury.cash-registers.view', 'description' => 'Ver Caixas'],
            ['name' => 'treasury.cash-registers.create', 'description' => 'Criar Caixas'],
            ['name' => 'treasury.cash-registers.edit', 'description' => 'Editar Caixas'],
            ['name' => 'treasury.cash-registers.delete', 'description' => 'Eliminar Caixas'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], [
                'description' => $perm['description'],
                'guard_name' => 'web'
            ]);
        }

        $this->command->info('✅ Permissões criadas!');
        $this->command->info('🔄 Atribuindo permissões aos roles...');

        // Atualizar Super Admin (todas as permissões)
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
            $this->command->info('✓ Super Admin atualizado');
        }

        // Atualizar Administrador Faturação
        $invoicingAdmin = Role::where('name', 'Administrador Faturação')->first();
        if ($invoicingAdmin) {
            $invoicingAdmin->givePermissionTo([
                'invoicing.warehouses.view', 'invoicing.warehouses.create', 'invoicing.warehouses.edit', 'invoicing.warehouses.delete',
                'invoicing.stock.view', 'invoicing.stock.edit',
                'invoicing.warehouse-transfer.view', 'invoicing.warehouse-transfer.create',
                'invoicing.inter-company-transfer.view', 'invoicing.inter-company-transfer.create',
                'invoicing.taxes.view', 'invoicing.taxes.edit',
                'invoicing.series.view', 'invoicing.series.edit',
                'invoicing.saft.view', 'invoicing.saft.generate',
                'treasury.payment-methods.view', 'treasury.payment-methods.create', 'treasury.payment-methods.edit', 'treasury.payment-methods.delete',
                'treasury.banks.view', 'treasury.banks.create', 'treasury.banks.edit', 'treasury.banks.delete',
                'treasury.cash-registers.view', 'treasury.cash-registers.create', 'treasury.cash-registers.edit', 'treasury.cash-registers.delete',
            ]);
            $this->command->info('✓ Administrador Faturação atualizado');
        }
        
        // Atualizar Caixa
        $cashier = Role::where('name', 'Caixa')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'treasury.payment-methods.view',
                'treasury.banks.view',
                'treasury.cash-registers.view',
            ]);
            $this->command->info('✓ Caixa atualizado');
        }

        // Atualizar Operador Stock
        $stockOperator = Role::where('name', 'Operador Stock')->first();
        if ($stockOperator) {
            $stockOperator->givePermissionTo([
                'invoicing.warehouses.view', 'invoicing.warehouses.create', 'invoicing.warehouses.edit',
                'invoicing.stock.view', 'invoicing.stock.edit',
                'invoicing.warehouse-transfer.view', 'invoicing.warehouse-transfer.create',
            ]);
            $this->command->info('✓ Operador Stock atualizado');
        }

        $this->command->info('✅ Roles atualizados com sucesso!');
    }
}
