<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, Tenant, Plan, Module};
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class MultiTenantTestSeeder extends Seeder
{
    /**
     * Seed para testar sistema multi-empresa por usuário
     * 
     * Cria:
     * - 2 Empresas (Empresa A e Empresa B)
     * - 1 Usuário com acesso às 2 empresas
     * - Módulos ativos para ambas empresas
     */
    public function run(): void
    {
        $this->command->info('🔧 Criando dados de teste para Multi-Tenant...');
        
        // Buscar plano básico (ou criar)
        $plan = Plan::first();
        if (!$plan) {
            $plan = Plan::create([
                'name' => 'Plano Básico',
                'slug' => 'basic',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'max_users' => 5,
                'is_active' => true,
            ]);
        }
        
        // Criar Empresa A
        $this->command->info('📦 Criando Empresa A...');
        $tenantA = Tenant::create([
            'name' => 'Empresa A - Comércio Geral',
            'slug' => 'empresa-a',
            'company_name' => 'Empresa A - Comércio Geral, Lda',
            'nif' => '5000000001',
            'email' => 'contato@empresa-a.vip',
            'phone' => '+244 923 000 001',
            'address' => 'Rua 1 de Maio, nº 100, Luanda',
            'city' => 'Luanda',
            'country' => 'Angola',
            'is_active' => true,
            'settings' => json_encode(['currency' => 'AOA', 'province' => 'Luanda']),
        ]);
        
        // Criar Empresa B
        $this->command->info('📦 Criando Empresa B...');
        $tenantB = Tenant::create([
            'name' => 'Empresa B - Serviços e Consultoria',
            'slug' => 'empresa-b',
            'company_name' => 'Empresa B - Serviços e Consultoria, Lda',
            'nif' => '5000000002',
            'email' => 'contato@empresa-b.vip',
            'phone' => '+244 923 000 002',
            'address' => 'Avenida 4 de Fevereiro, nº 200, Luanda',
            'city' => 'Luanda',
            'country' => 'Angola',
            'is_active' => true,
            'settings' => json_encode(['currency' => 'AOA', 'province' => 'Luanda']),
        ]);
        
        // Ativar módulo de Faturação para ambas empresas
        $invoicingModule = Module::where('slug', 'invoicing')->first();
        if ($invoicingModule) {
            $this->command->info('🔌 Ativando módulo de Faturação...');
            
            $tenantA->modules()->attach($invoicingModule->id, [
                'is_active' => true,
                'activated_at' => now(),
            ]);
            
            $tenantB->modules()->attach($invoicingModule->id, [
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }
        
        // Criar usuário de teste (ou usar existente)
        $this->command->info('👤 Criando/Atualizando usuário de teste...');
        $user = User::where('email', 'teste@multitenant.com')->first();
        
        if (!$user) {
            $user = User::create([
                'name' => 'Usuário Multi-Empresa',
                'email' => 'teste@multitenant.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }
        
        // Buscar role Admin
        $adminRole = Role::where('name', 'Admin')->first();
        
        // Associar usuário às 2 empresas
        $this->command->info('🔗 Associando usuário às empresas...');
        
        // Empresa A - Como Admin
        if (!$user->tenants()->where('tenant_id', $tenantA->id)->exists()) {
            $user->tenants()->attach($tenantA->id, [
                'role_id' => $adminRole?->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);
            $this->command->info('   ✅ Usuário adicionado à Empresa A como Admin');
        }
        
        // Empresa B - Como Admin
        if (!$user->tenants()->where('tenant_id', $tenantB->id)->exists()) {
            $user->tenants()->attach($tenantB->id, [
                'role_id' => $adminRole?->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);
            $this->command->info('   ✅ Usuário adicionado à Empresa B como Admin');
        }
        
        $this->command->info('');
        $this->command->info('✅ Dados de teste criados com sucesso!');
        $this->command->info('');
        $this->command->line('═══════════════════════════════════════════════════════');
        $this->command->info('📋 CREDENCIAIS DE TESTE:');
        $this->command->line('═══════════════════════════════════════════════════════');
        $this->command->info('Email: teste@multitenant.com');
        $this->command->info('Senha: password');
        $this->command->line('───────────────────────────────────────────────────────');
        $this->command->info('🏢 Empresas Disponíveis:');
        $this->command->info('   1. Empresa A - Comércio Geral');
        $this->command->info('   2. Empresa B - Serviços e Consultoria');
        $this->command->line('───────────────────────────────────────────────────────');
        $this->command->info('O usuário pode alternar entre as 2 empresas!');
        $this->command->line('═══════════════════════════════════════════════════════');
    }
}
