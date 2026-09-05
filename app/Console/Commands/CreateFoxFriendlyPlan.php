<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Module;
use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
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
    protected $description = 'Cria o plano FOX Friendly com 3 meses grátis e todos os módulos';

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
            'description' => '🦊 Plano promocional com 3 meses grátis! Acesso completo a todos os módulos do sistema.',
            'price_monthly' => 0, // Grátis nos primeiros 3 meses
            'price_quarterly' => 0,
            'price_semiannual' => 0,
            'price_yearly' => 0,
            'max_users' => 999, // Ilimitado
            'max_companies' => 50,
            'max_storage_mb' => 100000, // 100GB
            // A oferta tem tecto: 500 documentos fiscais. Sem isto, três meses
            // «com tudo aberto» davam para facturar um ano à borla. Só apanha
            // quem assinar a partir de agora — o tecto viaja na subscrição
            // (ver App\Support\AcordoDeSubscricao e Tenant::limiteDeDocumentos).
            'max_documents' => 500,
            'features' => [
                'Todos os módulos incluídos',
                '500 documentos fiscais',
                '999 utilizadores',
                '50 empresas',
                '100GB de armazenamento',
                '3 meses totalmente GRÁTIS',
                'Suporte prioritário',
                'Atualizações automáticas',
                'Backup diário',
                'Sem compromisso de permanência'
            ],
            'included_modules' => $allModules, // TODOS os módulos
            'is_active' => true,
            'is_featured' => true,
            'trial_days' => 90, // 3 meses = 90 dias
            'auto_activate' => true, // Activação automática imediata (sem aprovação manual)
            'order' => 1, // Primeiro na lista
        ]);
        
        $plan->save();

        // CRÍTICO: sincronizar também o PIVOT plan_module (fonte de verdade usada pelo
        // TenantModuleSyncService), não apenas o JSON included_modules. Sem isto, o plano
        // "tem" todos os módulos no JSON mas o sync de ativação não os reconhece.
        $moduleIds = Module::whereIn('slug', $allModules)->pluck('id')->toArray();
        $plan->modules()->sync($moduleIds);

        // Atualizar também empresas que já usam o FOX Friendly. O catálogo de
        // módulos cresce ao longo do tempo (ex.: Restaurante) e limitar a
        // atualização ao plano deixava subscrições gratuitas antigas sem os
        // novos módulos prometidos em "todos os módulos".
        $syncService = app(TenantModuleSyncService::class);
        $syncedTenants = 0;
        Tenant::whereHas('subscriptions', function ($query) use ($plan) {
            $query->where('plan_id', $plan->id)
                ->where('status', 'active');
        })->each(function (Tenant $tenant) use ($syncService, $plan, &$syncedTenants) {
            $syncService->syncToPlan($tenant, $plan);
            $syncedTenants++;
        });

        $this->newLine();
        $this->info('  - Pivot plan_module sincronizado: ' . count($moduleIds) . ' módulos');
        $this->info('  - Empresas FOX Friendly sincronizadas: ' . $syncedTenants);
        $this->info('✓ Plano FOX Friendly criado com sucesso!');
        $this->info('  - Nome: ' . $plan->name);
        $this->info('  - Slug: ' . $plan->slug);
        $this->info('  - Trial: ' . $plan->trial_days . ' dias (3 meses)');
        $this->info('  - Auto-activate: ' . ($plan->auto_activate ? 'SIM' : 'NÃO'));
        $this->info('  - Módulos: ' . count($plan->included_modules));
        $this->info('  - Usuários: ' . $plan->max_users);
        $this->info('  - Storage: ' . ($plan->max_storage_mb / 1024) . 'GB');
        $this->info('  - Documentos fiscais: ' . ($plan->max_documents ?? 'sem tecto'));
        
        return Command::SUCCESS;
    }
}
