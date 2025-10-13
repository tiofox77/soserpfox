<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use App\Models\Accounting\Period;
use App\Models\Tenant;

class PeriodSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();
        
        foreach ($tenants as $tenant) {
            $currentYear = now()->year;
            
            // Criar 12 períodos (meses) para o ano atual
            $months = [
                ['code' => 'JAN', 'name' => 'Janeiro ' . $currentYear, 'month' => 1],
                ['code' => 'FEV', 'name' => 'Fevereiro ' . $currentYear, 'month' => 2],
                ['code' => 'MAR', 'name' => 'Março ' . $currentYear, 'month' => 3],
                ['code' => 'ABR', 'name' => 'Abril ' . $currentYear, 'month' => 4],
                ['code' => 'MAI', 'name' => 'Maio ' . $currentYear, 'month' => 5],
                ['code' => 'JUN', 'name' => 'Junho ' . $currentYear, 'month' => 6],
                ['code' => 'JUL', 'name' => 'Julho ' . $currentYear, 'month' => 7],
                ['code' => 'AGO', 'name' => 'Agosto ' . $currentYear, 'month' => 8],
                ['code' => 'SET', 'name' => 'Setembro ' . $currentYear, 'month' => 9],
                ['code' => 'OUT', 'name' => 'Outubro ' . $currentYear, 'month' => 10],
                ['code' => 'NOV', 'name' => 'Novembro ' . $currentYear, 'month' => 11],
                ['code' => 'DEZ', 'name' => 'Dezembro ' . $currentYear, 'month' => 12],
            ];
            
            foreach ($months as $month) {
                $dateStart = date('Y-m-01', strtotime($currentYear . '-' . $month['month'] . '-01'));
                $dateEnd = date('Y-m-t', strtotime($currentYear . '-' . $month['month'] . '-01'));
                
                Period::create([
                    'tenant_id' => $tenant->id,
                    'code' => $month['code'] . '/' . $currentYear,
                    'name' => $month['name'],
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'state' => 'open',
                ]);
            }
            
            echo "✅ Criados 12 períodos para {$tenant->name} ({$currentYear})\n";
        }
    }
    
    /**
     * Run seeder for a specific tenant (used when creating new company)
     */
    public function runForTenant(int $tenantId): void
    {
        $currentYear = now()->year;
        
        $months = [
            ['code' => 'JAN', 'name' => 'Janeiro ' . $currentYear, 'month' => 1],
            ['code' => 'FEV', 'name' => 'Fevereiro ' . $currentYear, 'month' => 2],
            ['code' => 'MAR', 'name' => 'Março ' . $currentYear, 'month' => 3],
            ['code' => 'ABR', 'name' => 'Abril ' . $currentYear, 'month' => 4],
            ['code' => 'MAI', 'name' => 'Maio ' . $currentYear, 'month' => 5],
            ['code' => 'JUN', 'name' => 'Junho ' . $currentYear, 'month' => 6],
            ['code' => 'JUL', 'name' => 'Julho ' . $currentYear, 'month' => 7],
            ['code' => 'AGO', 'name' => 'Agosto ' . $currentYear, 'month' => 8],
            ['code' => 'SET', 'name' => 'Setembro ' . $currentYear, 'month' => 9],
            ['code' => 'OUT', 'name' => 'Outubro ' . $currentYear, 'month' => 10],
            ['code' => 'NOV', 'name' => 'Novembro ' . $currentYear, 'month' => 11],
            ['code' => 'DEZ', 'name' => 'Dezembro ' . $currentYear, 'month' => 12],
        ];
        
        foreach ($months as $month) {
            $dateStart = date('Y-m-01', strtotime($currentYear . '-' . $month['month'] . '-01'));
            $dateEnd = date('Y-m-t', strtotime($currentYear . '-' . $month['month'] . '-01'));
            
            Period::create([
                'tenant_id' => $tenantId,
                'code' => $month['code'] . '/' . $currentYear,
                'name' => $month['name'],
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'state' => 'open',
            ]);
        }
    }
}
