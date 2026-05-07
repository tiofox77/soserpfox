<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Tenant, User, Client, Product};
use Illuminate\Support\Facades\Hash;

class InvoicingTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Criar ou atualizar Tenant de teste
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'empresa-faturacao-test'],
            [
                'name' => 'Empresa Teste Faturação Angola',
                'email' => 'faturacao@empresaangola.vip',
                'company_name' => 'Empresa Faturação Angola Lda',
                'nif' => '5000000001',
                'phone' => '+244 923 456 789',
                'max_users' => 10,
                'max_storage_mb' => 5000,
                'is_active' => true,
            ]
        );

        // 2. Criar ou atualizar usuário admin vinculado ao tenant
        $user = User::updateOrCreate(
            ['email' => 'admin@faturacao.vip'],
            [
                'name' => 'Admin Faturação',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );
        
        // Vincular usuário ao tenant via tabela pivot (se não existir)
        if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $user->tenants()->attach($tenant->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }
        
        // Criar usuário adicional
        $user2 = User::updateOrCreate(
            ['email' => 'user@faturacao.vip'],
            [
                'name' => 'Utilizador Faturação',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );
        
        // Vincular segundo usuário ao tenant via tabela pivot
        if (!$user2->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $user2->tenants()->attach($tenant->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }

        // Criar usuário faturacao@empresaangola.vip
        $user3 = User::updateOrCreate(
            ['email' => 'faturacao@empresaangola.vip'],
            [
                'name' => 'Faturação Angola',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]
        );
        
        // Vincular terceiro usuário ao tenant via tabela pivot
        if (!$user3->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $user3->tenants()->attach($tenant->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }

        $this->command->info('✅ Tenant criado/atualizado: ' . $tenant->name);
        $this->command->info('👤 Admin: admin@faturacao.vip / password');
        $this->command->info('👤 User: user@faturacao.vip / password');
        $this->command->info('🔗 Usuários vinculados ao tenant');

        // 2.1 Criar e vincular módulo de faturação ao tenant
        // CORRIGIR: Atualizar slug de 'faturacao' para 'invoicing'
        \DB::table('modules')->where('slug', 'faturacao')->update(['slug' => 'invoicing']);
        
        $moduloFaturacao = \App\Models\Module::updateOrCreate(
            ['slug' => 'invoicing'],
            [
                'name' => 'Faturação',
                'description' => 'Módulo completo de faturação para Angola (Clientes, Produtos, Faturas, Pagamentos)',
                'icon' => 'file-invoice',
                'version' => '1.0.0',
                'order' => 1,
                'is_core' => false,
                'is_active' => true,
            ]
        );
        
        // Vincular módulo ao tenant
        if (!$tenant->modules()->where('module_id', $moduloFaturacao->id)->exists()) {
            $tenant->modules()->attach($moduloFaturacao->id, [
                'is_active' => true,
                'activated_at' => now(),
            ]);
        } else {
            $this->command->info('✅ Módulo de Faturação já estava vinculado');
        }

        // 3. Criar clientes de teste (tabela: invoicing_clients)
        $client1 = \App\Models\Client::updateOrCreate(
            ['nif' => '5000123456'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'pessoa_juridica',
                'name' => 'Cliente Angola SARL',
            'phone' => '+244 222 123 456',
            'mobile' => '+244 923 111 222',
            'address' => 'Rua da Independência, 123, Luanda',
            'city' => 'Luanda',
            'province' => 'Luanda',
            'country' => 'Angola',
            'tax_regime' => 'geral',
            'is_iva_subject' => true,
            'credit_limit' => 1000000.00, // 1M Kz
            'payment_term_days' => 30,
            'is_active' => true,
        ]);

        $cliente2 = Client::updateOrCreate(
            ['nif' => '5000987654'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'pessoa_fisica',
                'name' => 'João Manuel Silva',
            'email' => 'joao.silva@email.vip',
            'phone' => '+244 924 555 666',
            'address' => 'Bairro Maculusso, Luanda',
            'city' => 'Luanda',
            'province' => 'Luanda',
            'country' => 'Angola',
            'tax_regime' => 'simplificado',
            'is_iva_subject' => true,
            'credit_limit' => 500000.00, // 500K Kz
            'payment_term_days' => 15,
            'is_active' => true,
        ]);

        $this->command->info('✅ 2 Clientes criados');

        // 4. Criar produtos/serviços
        $produto1 = Product::updateOrCreate(
            ['code' => 'ERP-IMP-001'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'servico',
            'name' => 'Implementação Sistema ERP',
            'description' => 'Serviço completo de implementação de sistema ERP empresarial',
            'category' => 'Software',
            'price' => 250000.00, // 250.000 Kz por hora
            'cost' => 100000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'unit' => 'HR',
            'is_active' => true,
        ]);

        $produto2 = Product::updateOrCreate(
            ['code' => 'SUP-MEN-001'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'servico',
            'name' => 'Suporte Técnico Mensal',
            'description' => 'Serviço de suporte técnico e manutenção mensal do sistema',
            'category' => 'Suporte',
            'price' => 50000.00, // 50.000 Kz/mês
            'cost' => 20000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'unit' => 'MÊS',
            'is_active' => true,
        ]);

        $produto3 = Product::updateOrCreate(
            ['code' => 'FORM-USR-001'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'servico',
            'name' => 'Formação de Utilizadores',
            'description' => 'Sessão de formação para utilizadores do sistema',
            'category' => 'Formação',
            'price' => 150000.00, // 150.000 Kz/dia
            'cost' => 60000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'unit' => 'DIA',
            'is_active' => true,
        ]);

        $produto4 = Product::updateOrCreate(
            ['code' => 'HW-SRV-001'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'produto',
            'name' => 'Servidor Dell PowerEdge',
            'description' => 'Servidor empresarial Dell PowerEdge R740',
            'category' => 'Hardware',
            'price' => 5000000.00, // 5M Kz
            'cost' => 3500000.00,
            'is_iva_subject' => true,
            'iva_rate' => 14.00,
            'manage_stock' => true,
            'stock_quantity' => 5,
            'minimum_stock' => 2,
            'unit' => 'UN',
            'is_active' => true,
        ]);

        $this->command->info('✅ 4 Produtos/Serviços criados');

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('🇦🇴 MÓDULO DE FATURAÇÃO ANGOLA - DADOS DE TESTE');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('👥 USUÁRIOS:');
        $this->command->info('   📧 Admin: admin@faturacao.vip / password');
        $this->command->info('   📧 User:  user@faturacao.vip / password');
        $this->command->info('');
        $this->command->info('🏢 Tenant: ' . $tenant->name);
        $this->command->info('📋 NIF Empresa: ' . $tenant->nif);
        $this->command->info('');
        $this->command->info('👥 Clientes:');
        $this->command->info('   1. ' . $cliente1->name . ' (NIF: ' . $cliente1->nif . ')');
        $this->command->info('   2. ' . $cliente2->name . ' (NIF: ' . $cliente2->nif . ')');
        $this->command->info('');
        $this->command->info('📦 Produtos/Serviços:');
        $this->command->info('   1. ' . $produto1->code . ' - ' . number_format($produto1->price, 2) . ' Kz/' . $produto1->unit);
        $this->command->info('   2. ' . $produto2->code . ' - ' . number_format($produto2->price, 2) . ' Kz/' . $produto2->unit);
        $this->command->info('   3. ' . $produto3->code . ' - ' . number_format($produto3->price, 2) . ' Kz/' . $produto3->unit);
        $this->command->info('   4. ' . $produto4->code . ' - ' . number_format($produto4->price, 2) . ' Kz/' . $produto4->unit);
        $this->command->info('');
        $this->command->info('💰 Moeda: Kwanza (Kz)');
        $this->command->info('📊 IVA: 14%');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
