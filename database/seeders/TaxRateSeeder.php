<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{TaxRate, Tenant};

class TaxRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obter todos os tenants
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Taxas de IVA de Angola
            $taxRates = [
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'IVA 14%',
                    'rate' => 14.00,
                    'description' => 'Taxa Geral de IVA - Aplicável à generalidade dos bens e serviços',
                    'type' => 'iva',
                    'is_default' => true,
                    'is_active' => true,
                    'order' => 1,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'IVA 7%',
                    'rate' => 7.00,
                    'description' => 'Taxa Reduzida - Bens de primeira necessidade',
                    'type' => 'iva',
                    'is_default' => false,
                    'is_active' => true,
                    'order' => 2,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'IVA 5%',
                    'rate' => 5.00,
                    'description' => 'Taxa Especial - Aplicações específicas',
                    'type' => 'iva',
                    'is_default' => false,
                    'is_active' => true,
                    'order' => 3,
                ],
            ];

            foreach ($taxRates as $taxRate) {
                TaxRate::updateOrCreate(
                    [
                        'tenant_id' => $taxRate['tenant_id'],
                        'name' => $taxRate['name'],
                    ],
                    $taxRate
                );
            }
        }

        $this->command->info('Taxas de IVA criadas com sucesso para todos os tenants!');
    }
}
