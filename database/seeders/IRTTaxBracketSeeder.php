<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HR\IRTTaxBracket;
use App\Models\Tenant;

class IRTTaxBracketSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        // Escalões IRT Angola (Lei nº 7/15 - Lei Geral do Trabalho)
        $brackets = [
            ['bracket_number' => 1, 'min_income' => 0,       'max_income' => 70000,   'fixed_amount' => 0,     'tax_rate' => 0],
            ['bracket_number' => 2, 'min_income' => 70001,   'max_income' => 100000,  'fixed_amount' => 0,     'tax_rate' => 0.10],
            ['bracket_number' => 3, 'min_income' => 100001,  'max_income' => 150000,  'fixed_amount' => 3000,  'tax_rate' => 0.13],
            ['bracket_number' => 4, 'min_income' => 150001,  'max_income' => 200000,  'fixed_amount' => 9500,  'tax_rate' => 0.16],
            ['bracket_number' => 5, 'min_income' => 200001,  'max_income' => 300000,  'fixed_amount' => 17500, 'tax_rate' => 0.18],
            ['bracket_number' => 6, 'min_income' => 300001,  'max_income' => 500000,  'fixed_amount' => 35500, 'tax_rate' => 0.19],
            ['bracket_number' => 7, 'min_income' => 500001,  'max_income' => 1000000, 'fixed_amount' => 73500, 'tax_rate' => 0.20],
            ['bracket_number' => 8, 'min_income' => 1000001, 'max_income' => 1500000, 'fixed_amount' => 173500,'tax_rate' => 0.21],
            ['bracket_number' => 9, 'min_income' => 1500001, 'max_income' => 2000000, 'fixed_amount' => 278500,'tax_rate' => 0.22],
            ['bracket_number' => 10,'min_income' => 2000001, 'max_income' => 2500000, 'fixed_amount' => 388500,'tax_rate' => 0.23],
            ['bracket_number' => 11,'min_income' => 2500001, 'max_income' => 5000000, 'fixed_amount' => 503500,'tax_rate' => 0.24],
            ['bracket_number' => 12,'min_income' => 5000001, 'max_income' => 10000000,'fixed_amount' => 1103500,'tax_rate' => 0.245],
            ['bracket_number' => 13,'min_income' => 10000001,'max_income' => null,     'fixed_amount' => 2328500,'tax_rate' => 0.25],
        ];

        foreach ($tenants as $tenant) {
            foreach ($brackets as $bracket) {
                IRTTaxBracket::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'bracket_number' => $bracket['bracket_number'],
                    ],
                    array_merge($bracket, [
                        'tenant_id' => $tenant->id,
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}
