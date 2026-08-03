<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;

/**
 * Garante que a TESOURARIA acompanha sempre a FATURAÇÃO:
 *  - cria o módulo 'treasury' se não existir;
 *  - adiciona 'treasury' a todos os PLANOS que têm 'invoicing';
 *  - ativa 'treasury' em todos os TENANTS que têm 'invoicing' ativo
 *    (concede também as permissões de tesouraria às roles).
 *
 * Idempotente.
 */
class BundleTreasuryWithInvoicing extends Command
{
    protected $signature = 'treasury:bundle';

    protected $description = 'Garante que a Tesouraria acompanha a Faturação em planos e tenants';

    public function handle(TenantModuleSyncService $sync): int
    {
        // 1) Garantir que o módulo treasury existe
        $treasury = Module::firstOrCreate(
            ['slug' => 'treasury'],
            [
                'name' => 'Tesouraria',
                'description' => 'Gestão de caixas, bancos, métodos de pagamento e transações.',
                'icon' => 'wallet',
                'is_core' => false,
                'is_active' => true,
                'order' => 12,
                'dependencies' => ['invoicing'],
            ]
        );
        $invoicing = Module::where('slug', 'invoicing')->first();
        if (!$invoicing) {
            $this->error("Módulo 'invoicing' não existe. Corra o ModuleSeeder primeiro.");
            return self::FAILURE;
        }
        $this->info("Módulo treasury #{$treasury->id} | invoicing #{$invoicing->id}");

        // 2) PLANOS com invoicing → adicionar treasury
        $this->newLine();
        $this->info('— Planos —');
        foreach (Plan::with('modules')->get() as $plan) {
            $slugs = $plan->modules->pluck('slug')->toArray();
            $hasInvoicing = in_array('invoicing', $slugs);
            $hasTreasury  = in_array('treasury', $slugs);

            if ($hasInvoicing && !$hasTreasury) {
                $plan->modules()->syncWithoutDetaching([$treasury->id]);

                // Atualizar também o JSON included_modules, se aplicável
                $inc = $plan->included_modules ?? [];
                if (is_array($inc) && (in_array('invoicing', $inc) || in_array('faturacao', $inc)) && !in_array('treasury', $inc)) {
                    $inc[] = 'treasury';
                    $plan->included_modules = $inc;
                    $plan->save();
                }
                $this->line("  ✓ {$plan->slug} — treasury adicionado");
            } else {
                $this->line("  • {$plan->slug} — " . ($hasInvoicing ? 'já tem treasury' : 'sem invoicing, ignorado'));
            }
        }

        // 3) TENANTS com invoicing ativo → ativar treasury
        $this->newLine();
        $this->info('— Tenants —');
        foreach (Tenant::all() as $tenant) {
            $hasInvoicing = $tenant->modules()
                ->where('slug', 'invoicing')->wherePivot('is_active', true)->exists();
            if (!$hasInvoicing) {
                $this->line("  • #{$tenant->id} {$tenant->name} — sem invoicing ativo, ignorado");
                continue;
            }

            $hasTreasury = $tenant->modules()
                ->where('slug', 'treasury')->wherePivot('is_active', true)->exists();
            if ($hasTreasury) {
                $this->line("  • #{$tenant->id} {$tenant->name} — treasury já ativo");
                continue;
            }

            $sync->activateModule($tenant, 'treasury');
            $this->line("  ✓ #{$tenant->id} {$tenant->name} — treasury ativado");
        }

        $this->newLine();
        $this->info('Concluído: Tesouraria agora acompanha a Faturação.');
        return self::SUCCESS;
    }
}
