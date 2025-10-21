<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CostCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = \App\Models\Tenant::all();
        
        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant->id);
        }
    }
    
    /**
     * Seed para tenant específico (usado ao criar novo tenant)
     */
    public function seedForTenant(int $tenantId): void
    {
        $costCenters = [
            // Centros de Receita
            [
                'code' => 'CR-001',
                'name' => 'Vendas',
                'description' => 'Centro de custo para receitas de vendas',
                'type' => 'revenue',
                'is_active' => true,
            ],
            [
                'code' => 'CR-002',
                'name' => 'Serviços',
                'description' => 'Centro de custo para prestação de serviços',
                'type' => 'revenue',
                'is_active' => true,
            ],
            
            // Centros de Custo Operacionais
            [
                'code' => 'CC-001',
                'name' => 'Produção',
                'description' => 'Custos de produção e fabricação',
                'type' => 'cost',
                'is_active' => true,
            ],
            [
                'code' => 'CC-002',
                'name' => 'Comercial',
                'description' => 'Custos do departamento comercial',
                'type' => 'cost',
                'is_active' => true,
            ],
            [
                'code' => 'CC-003',
                'name' => 'Marketing',
                'description' => 'Custos com marketing e publicidade',
                'type' => 'cost',
                'is_active' => true,
            ],
            [
                'code' => 'CC-004',
                'name' => 'Logística',
                'description' => 'Custos com transporte e armazenamento',
                'type' => 'cost',
                'is_active' => true,
            ],
            
            // Centros de Custo Administrativos
            [
                'code' => 'CA-001',
                'name' => 'Administrativo',
                'description' => 'Custos administrativos gerais',
                'type' => 'support',
                'is_active' => true,
            ],
            [
                'code' => 'CA-002',
                'name' => 'Recursos Humanos',
                'description' => 'Custos do departamento de RH',
                'type' => 'support',
                'is_active' => true,
            ],
            [
                'code' => 'CA-003',
                'name' => 'Financeiro',
                'description' => 'Custos do departamento financeiro',
                'type' => 'support',
                'is_active' => true,
            ],
            [
                'code' => 'CA-004',
                'name' => 'TI',
                'description' => 'Custos com tecnologia da informação',
                'type' => 'support',
                'is_active' => true,
            ],
            [
                'code' => 'CA-005',
                'name' => 'Jurídico',
                'description' => 'Custos com assessoria jurídica',
                'type' => 'support',
                'is_active' => true,
            ],
        ];
        
        foreach ($costCenters as $cc) {
            DB::table('cost_centers')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'code' => $cc['code'],
                ],
                array_merge($cc, [
                    'tenant_id' => $tenantId,
                    'parent_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
