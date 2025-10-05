<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Module;
use Illuminate\Console\Command;

class CreateFoxFriendlyPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:fox-friendly-plan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria o plano FOX Friendly com 6 meses grátis e todos os módulos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Criando plano FOX Friendly...');
        
        // Buscar todos os módulos disponíveis
        $allModules = Module::pluck('slug')->toArray();
        
        $this->info('Módulos disponíveis: ' . implode(', ', $allModules));
        
        // Verificar se já existe
        $existing = Plan::where('slug', 'fox-friendly')->first();
        
        if ($existing) {
            $this->warn('Plano FOX Friendly já existe. Atualizando...');
            $plan = $existing;
        } else {
            $plan = new Plan();
        }
        
        $plan->fill([
            'name' => '🦊 FOX Friendly',
            'slug' => 'fox-friendly',
            'description' => '🦊 Plano promocional com 6 meses grátis! Acesso completo a todos os módulos do sistema.',
            'price_monthly' => 0, // Grátis nos primeiros 6 meses
            'price_quarterly' => 0,
            'price_semiannual' => 0,
            'price_yearly' => 0,
            'max_users' => 999, // Ilimitado
            'max_companies' => 50,
            'max_storage_mb' => 100000, // 100GB
            'features' => [
                'Todos os módulos incluídos',
                '999 utilizadores',
                '50 empresas',
                '100GB de armazenamento',
                '6 meses totalmente GRÁTIS',
                'Suporte prioritário',
                'Atualizações automáticas',
                'Backup diário',
                'Sem compromisso de permanência'
            ],
            'included_modules' => $allModules, // TODOS os módulos
            'is_active' => true,
            'is_featured' => true,
            'trial_days' => 180, // 6 meses = 180 dias
            'order' => 1, // Primeiro na lista
        ]);
        
        $plan->save();
        
        $this->newLine();
        $this->info('✓ Plano FOX Friendly criado com sucesso!');
        $this->info('  - Nome: ' . $plan->name);
        $this->info('  - Slug: ' . $plan->slug);
        $this->info('  - Trial: ' . $plan->trial_days . ' dias (6 meses)');
        $this->info('  - Módulos: ' . count($plan->included_modules));
        $this->info('  - Usuários: ' . $plan->max_users);
        $this->info('  - Storage: ' . ($plan->max_storage_mb / 1024) . 'GB');
        
        return Command::SUCCESS;
    }
}
