<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;

class FixModuleSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:module-slugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige os slugs dos módulos nos planos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Corrigindo slugs dos módulos nos planos...');
        
        // Starter Plan
        $starter = Plan::where('slug', 'starter')->first();
        if ($starter) {
            $starter->included_modules = ['invoicing'];
            $starter->save();
            $this->info("✓ Starter atualizado: ['invoicing']");
        }
        
        // Professional Plan
        $professional = Plan::where('slug', 'professional')->first();
        if ($professional) {
            $professional->included_modules = ['invoicing', 'rh', 'inventario'];
            $professional->save();
            $this->info("✓ Professional atualizado: ['invoicing', 'rh', 'inventario']");
        }
        
        // Enterprise Plan
        $enterprise = Plan::where('slug', 'enterprise')->first();
        if ($enterprise) {
            $enterprise->included_modules = ['invoicing', 'rh', 'contabilidade', 'oficina', 'crm', 'inventario', 'compras', 'projetos'];
            $enterprise->save();
            $this->info("✓ Enterprise atualizado: todos os módulos");
        }
        
        $this->newLine();
        $this->info('Módulos dos planos corrigidos com sucesso!');
        
        return Command::SUCCESS;
    }
}
