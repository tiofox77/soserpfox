<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxSeeder extends Seeder
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
        $taxes = [
            // IVA (Imposto sobre o Valor Acrescentado)
            [
                'code' => 'IVA14',
                'name' => 'IVA 14%',
                'type' => 'vat',
                'rate' => 14.00,
                'active' => true,
            ],
            [
                'code' => 'IVA0',
                'name' => 'IVA Isento',
                'type' => 'vat',
                'rate' => 0.00,
                'active' => true,
            ],
            
            // Retenção na Fonte - IRT (Imposto sobre o Rendimento do Trabalho)
            [
                'code' => 'IRT6.5',
                'name' => 'IRT 6,5%',
                'type' => 'withholding',
                'rate' => 6.50,
                'active' => true,
            ],
            [
                'code' => 'IRT12.5',
                'name' => 'IRT 12,5%',
                'type' => 'withholding',
                'rate' => 12.50,
                'active' => true,
            ],
            [
                'code' => 'IRT17.5',
                'name' => 'IRT 17,5%',
                'type' => 'withholding',
                'rate' => 17.50,
                'active' => true,
            ],
            
            // Retenção na Fonte - Rendimentos de Capitais
            [
                'code' => 'RFC10',
                'name' => 'Retenção Capitais 10%',
                'type' => 'withholding',
                'rate' => 10.00,
                'active' => true,
            ],
            
            // Retenção na Fonte - Prestação de Serviços
            [
                'code' => 'RFS6.5',
                'name' => 'Retenção Serviços 6,5%',
                'type' => 'withholding',
                'rate' => 6.50,
                'active' => true,
            ],
            
            // Imposto de Selo
            [
                'code' => 'IS',
                'name' => 'Imposto de Selo',
                'type' => 'other',
                'rate' => 0.00, // Varia conforme operação
                'active' => true,
            ],
            
            // Imposto Industrial (empresas)
            [
                'code' => 'II30',
                'name' => 'Imposto Industrial 30%',
                'type' => 'other',
                'rate' => 30.00,
                'active' => true,
            ],
        ];
        
        foreach ($taxes as $tax) {
            DB::table('accounting_taxes')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'code' => $tax['code'],
                ],
                array_merge($tax, [
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
