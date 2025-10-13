<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Accounting\AccountSeeder;
use Database\Seeders\Accounting\JournalSeeder;
use Database\Seeders\Accounting\PeriodSeeder;
use Database\Seeders\Accounting\IntegrationMappingSeeder;

class AccountingSeeder extends Seeder
{
    /**
     * Seed the Accounting module.
     * 
     * Order:
     * 1. Accounts (Plano de Contas)
     * 2. Journals (Diários)
     * 3. Periods (Períodos)
     */
    public function run(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════╗\n";
        echo "║     SEEDING ACCOUNTING MODULE (CONTABILIDADE)         ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n";
        echo "\n";
        
        $this->command->info('🎯 INICIANDO SEED DO MÓDULO DE CONTABILIDADE');
        $this->command->newLine();

        $this->call([
            AccountSeeder::class,
            JournalSeeder::class,
            PeriodSeeder::class,
            IntegrationMappingSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('✅ SEED DO MÓDULO DE CONTABILIDADE COMPLETO!');
        echo "║  - 71 Accounts (SNC Chart)                            ║\n";
        echo "║  - 6 Journals (VEND, COMP, CX, BCO, SAL, AJ)          ║\n";
        echo "║  - 12 Periods (" . now()->year . ")                                     ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }
}
