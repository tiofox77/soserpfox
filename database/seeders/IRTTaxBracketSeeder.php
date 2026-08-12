<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HR\IRTTaxBracket;
use App\Models\Tenant;

/**
 * Tabela IRT Angola — Grupo A (em vigor).
 *
 * Isenção até 150.000 Kz. Método: IRT = parcela_fixa + taxa × (rendimento − limite_inferior).
 * As parcelas fixas são o imposto ACUMULADO no início de cada escalão, calculadas para
 * garantir progressividade CONTÍNUA (sem saltos) a partir da isenção e das taxas marginais.
 *
 * Verificação de continuidade (imposto no topo de cada escalão = parcela fixa do seguinte):
 *   200.000→8.000  300.000→26.000  500.000→64.000  1.000.000→164.000  ... 10.000.000→2.319.000
 */
class IRTTaxBracketSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        $brackets = [
            ['bracket_number' => 1,  'min_income' => 0,         'max_income' => 150000,   'fixed_amount' => 0,        'tax_rate' => 0],
            ['bracket_number' => 2,  'min_income' => 150000,    'max_income' => 200000,   'fixed_amount' => 0,        'tax_rate' => 0.16],
            ['bracket_number' => 3,  'min_income' => 200000,    'max_income' => 300000,   'fixed_amount' => 8000,     'tax_rate' => 0.18],
            ['bracket_number' => 4,  'min_income' => 300000,    'max_income' => 500000,   'fixed_amount' => 26000,    'tax_rate' => 0.19],
            ['bracket_number' => 5,  'min_income' => 500000,    'max_income' => 1000000,  'fixed_amount' => 64000,    'tax_rate' => 0.20],
            ['bracket_number' => 6,  'min_income' => 1000000,   'max_income' => 1500000,  'fixed_amount' => 164000,   'tax_rate' => 0.21],
            ['bracket_number' => 7,  'min_income' => 1500000,   'max_income' => 2000000,  'fixed_amount' => 269000,   'tax_rate' => 0.22],
            ['bracket_number' => 8,  'min_income' => 2000000,   'max_income' => 2500000,  'fixed_amount' => 379000,   'tax_rate' => 0.23],
            ['bracket_number' => 9,  'min_income' => 2500000,   'max_income' => 5000000,  'fixed_amount' => 494000,   'tax_rate' => 0.24],
            ['bracket_number' => 10, 'min_income' => 5000000,   'max_income' => 10000000, 'fixed_amount' => 1094000,  'tax_rate' => 0.245],
            ['bracket_number' => 11, 'min_income' => 10000000,  'max_income' => null,     'fixed_amount' => 2319000,  'tax_rate' => 0.25],
        ];

        foreach ($tenants as $tenant) {
            // Limpar escalões antigos (a tabela passada tinha 13 escalões com outra estrutura)
            // para não deixar linhas obsoletas que interfiram na seleção do escalão.
            IRTTaxBracket::where('tenant_id', $tenant->id)->delete();

            foreach ($brackets as $bracket) {
                IRTTaxBracket::create(array_merge($bracket, [
                    'tenant_id' => $tenant->id,
                    'is_active' => true,
                ]));
            }
        }
    }
}
