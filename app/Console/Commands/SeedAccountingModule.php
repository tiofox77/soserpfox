<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\AccountingSeeder;

class SeedAccountingModule extends Command
{
    protected $signature = 'accounting:seed
                            {--force : Force seeding in production}';

    protected $description = 'Seed the Accounting module (Accounts, Journals, Periods)';

    public function handle()
    {
        if ($this->getLaravel()->environment('production') && !$this->option('force')) {
            $this->error('⚠️  Running in production! Use --force to proceed.');
            return 1;
        }

        $this->info('🚀 Starting Accounting Module Seeding...');
        $this->newLine();

        try {
            $this->call('db:seed', [
                '--class' => AccountingSeeder::class
            ]);

            $this->newLine();
            $this->info('✅ Accounting module seeded successfully!');
            $this->newLine();
            
            $this->table(
                ['Module', 'Items Seeded'],
                [
                    ['Accounts (SNC)', '71 contas'],
                    ['Journals', '6 diários'],
                    ['Periods', '12 períodos (' . now()->year . ')'],
                ]
            );
            
            $this->newLine();
            $this->info('📍 Access: http://soserp.test/accounting/dashboard');
            $this->newLine();

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error seeding Accounting module:');
            $this->error($e->getMessage());
            return 1;
        }
    }
}
