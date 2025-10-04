<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Plano básico ideal para pequenas empresas',
                'price_monthly' => 29.90,
                'price_yearly' => 299.00,
                'max_users' => 3,
                'max_companies' => 1, // 1 empresa apenas
                'max_storage_mb' => 1000, // 1GB
                'features' => [
                    'Módulo de Faturação completo',
                    '3 utilizadores',
                    '1 empresa',
                    '1GB de armazenamento',
                    'Suporte por email',
                ],
                'included_modules' => ['faturacao'],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 14,
                'order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Plano completo para empresas em crescimento',
                'price_monthly' => 79.90,
                'price_yearly' => 799.00,
                'max_users' => 10,
                'max_companies' => 3, // 3 empresas (multi-empresa)
                'max_storage_mb' => 5000, // 5GB
                'features' => [
                    'Módulos: Faturação + RH + Contabilidade',
                    '10 utilizadores',
                    'Até 3 empresas',
                    '5GB de armazenamento',
                    'Suporte prioritário',
                    'Relatórios avançados',
                ],
                'included_modules' => ['faturacao', 'rh', 'contabilidade'],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 14,
                'order' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Solução completa para grandes empresas',
                'price_monthly' => 149.90,
                'price_yearly' => 1499.00,
                'max_users' => 50,
                'max_companies' => 10, // 10 empresas (multi-empresa)
                'max_storage_mb' => 20000, // 20GB
                'features' => [
                    'Todos os módulos incluídos',
                    'Até 50 utilizadores',
                    'Até 10 empresas',
                    '20GB de armazenamento',
                    'Suporte premium 24/7',
                    'Personalização avançada',
                    'API Access',
                ],
                'included_modules' => ['faturacao', 'rh', 'contabilidade', 'oficina', 'crm', 'inventario', 'compras', 'projetos'],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 30,
                'order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solução enterprise com recursos ilimitados',
                'price_monthly' => 299.90,
                'price_yearly' => 2999.00,
                'max_users' => 999,
                'max_companies' => 999, // Ilimitado (multi-empresa)
                'max_storage_mb' => 100000, // 100GB
                'features' => [
                    'Todos os módulos',
                    'Utilizadores ilimitados',
                    'Empresas ilimitadas',
                    '100GB+ de armazenamento',
                    'Suporte dedicado',
                    'Onboarding personalizado',
                    'SLA garantido',
                    'Infraestrutura dedicada',
                ],
                'included_modules' => ['faturacao', 'rh', 'contabilidade', 'oficina', 'crm', 'inventario', 'compras', 'projetos'],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 30,
                'order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
