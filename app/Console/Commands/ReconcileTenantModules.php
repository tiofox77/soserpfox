<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;

/**
 * Reconcilia o estado de módulos e permissões Spatie de um tenant com o seu
 * plano ativo. Útil para corrigir tenants que ficaram com permissões residuais
 * de módulos antes do TenantModuleSyncService existir.
 *
 *   php artisan tenant:reconcile-modules            # todos os tenants
 *   php artisan tenant:reconcile-modules 60         # tenant específico
 *   php artisan tenant:reconcile-modules --dry-run  # só mostra o que faria
 */
class ReconcileTenantModules extends Command
{
    protected $signature = 'tenant:reconcile-modules
                            {tenant_id? : ID do tenant (omitir para todos)}
                            {--dry-run : Apenas simular sem aplicar}';

    protected $description = 'Reconcilia módulos e permissões do tenant com o plano ativo (revoga residuais).';

    public function handle(TenantModuleSyncService $service): int
    {
        $tenantId = $this->argument('tenant_id');
        $dryRun = (bool) $this->option('dry-run');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return self::SUCCESS;
        }

        $this->info("Reconciliando " . $tenants->count() . " tenant(s)" . ($dryRun ? ' [DRY-RUN]' : '') . '...');

        foreach ($tenants as $tenant) {
            $this->line("\n— Tenant #{$tenant->id} {$tenant->name}");

            $sub = $tenant->activeSubscription()->first();
            if (!$sub || !$sub->plan) {
                $this->warn("   sem subscription ativa — saltando.");
                continue;
            }

            $this->line("   plano: {$sub->plan->name}");
            $planModules = $sub->plan->modules()->pluck('slug')->toArray();
            $tenantActive = $tenant->modules()->wherePivot('is_active', true)->pluck('slug')->toArray();
            $toDeactivate = array_diff($tenantActive, $planModules);
            $toActivate = array_diff($planModules, $tenantActive);

            $this->line("   ativos no tenant: " . (empty($tenantActive) ? '(nenhum)' : implode(', ', $tenantActive)));
            $this->line("   no plano:        " . (empty($planModules) ? '(nenhum)' : implode(', ', $planModules)));

            if (!empty($toActivate)) {
                $this->line("   <fg=green>+ a activar (pivot):</> " . implode(', ', $toActivate));
            }
            if (!empty($toDeactivate)) {
                $this->line("   <fg=red>- a desativar (pivot):</> " . implode(', ', $toDeactivate));
            }
            if (empty($toDeactivate) && empty($toActivate)) {
                $this->line('   pivot já consistente — apenas sanitizar permissões residuais');
            }

            if ($dryRun) {
                $this->comment('   [DRY-RUN] sem alterações');
                continue;
            }

            // reconcile() força grant/revoke de permissões em TODOS os módulos
            // (não apenas os do diff), para limpar permissões residuais.
            $result = $service->reconcile($tenant);
            $this->info('   ✓ ativadas: ' . count($result['activated']) . ' | desativadas: ' . count($result['deactivated']));
        }

        $this->newLine();
        $this->info('Concluído.');
        return self::SUCCESS;
    }
}
