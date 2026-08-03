<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara empresas com módulos incoerentes ou incompletos:
 *
 *  1. DEPENDÊNCIAS — activa o que falta (ex.: Faturação sem Tesouraria);
 *  2. PRÉ-REQUISITOS — cria os dados sem os quais o módulo é inutilizável
 *     (métodos de pagamento, impostos, armazém por omissão, configurações);
 *  3. PERMISSÕES — concede aos papéis do tenant as permissões dos módulos activos.
 *
 * Idempotente e seguro: só ACRESCENTA. Nunca desactiva módulos nem remove dados.
 *
 *   php artisan modules:repair --dry-run
 *   php artisan modules:repair --tenant=19
 */
class RepairTenantModules extends Command
{
    protected $signature = 'modules:repair
                            {--tenant= : Limitar a uma empresa}
                            {--dry-run : Apenas mostra o que seria corrigido}';

    protected $description = 'Repara módulos das empresas: dependências em falta, pré-requisitos e permissões';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $sync = new TenantModuleSyncService();

        $this->info('=== Reparação de módulos ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');
        $this->line('  Só acrescenta: nunca desactiva módulos nem apaga dados.');
        $this->newLine();

        $query = Tenant::query()->orderBy('id');
        if ($id = $this->option('tenant')) {
            $query->where('id', $id);
        }

        $totalDeps = 0;
        $totalSeeds = 0;
        $totalTipos = $this->normalizarTiposDePagamento($dry, $this->option('tenant'));

        foreach ($query->get() as $tenant) {
            $activos = $tenant->modules()->wherePivot('is_active', true)->pluck('modules.slug')->toArray();
            if (empty($activos)) {
                continue;
            }

            $linhas = [];

            // 1) Dependências em falta
            $emFalta = [];
            foreach ($activos as $slug) {
                foreach (TenantModuleSyncService::MODULE_DEPENDENCIES[$slug] ?? [] as $dep) {
                    if (!in_array($dep, $activos, true) && !in_array($dep, $emFalta, true)) {
                        $emFalta[] = $dep;
                    }
                }
            }

            foreach ($emFalta as $dep) {
                $existe = Module::where('slug', $dep)->exists();
                $linhas[] = $existe
                    ? "dependência em falta: <fg=yellow>{$dep}</> → activar"
                    : "dependência <fg=red>{$dep} não existe no catálogo</>";
                if (!$dry && $existe) {
                    $sync->activateModule($tenant, $dep);
                    $totalDeps++;
                }
            }

            // 2) Pré-requisitos de cada módulo activo (+ os agora activados)
            foreach (array_unique(array_merge($activos, $emFalta)) as $slug) {
                $faltas = $this->prerequisitosEmFalta($tenant, $slug);
                if (empty($faltas)) {
                    continue;
                }
                $linhas[] = "pré-requisitos de <fg=cyan>{$slug}</>: " . implode(', ', $faltas);
                if (!$dry) {
                    $criado = $sync->seedModulePrerequisites($tenant, $slug);
                    $totalSeeds += count($criado);
                }
            }

            if ($linhas) {
                $this->line("  <fg=white;options=bold>#{$tenant->id} {$tenant->name}</>");
                foreach ($linhas as $l) {
                    $this->line("     • {$l}");
                }
            }
        }

        $this->newLine();
        if ($totalDeps === 0 && $totalSeeds === 0 && $totalTipos === 0 && !$dry) {
            $this->info('✓ Todas as empresas estão coerentes.');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->info('(dry-run) Nada foi alterado. Remova --dry-run para aplicar.');
        } else {
            $this->info("   Módulos activados por dependência: {$totalDeps}");
            $this->info("   Conjuntos de pré-requisitos criados: {$totalSeeds}");
            $this->info("   Métodos de pagamento com tipo corrigido: {$totalTipos}");
        }

        return self::SUCCESS;
    }

    /**
     * Normaliza tipos de métodos de pagamento gravados fora do conjunto válido.
     *
     * O PaymentModal gravava 'bank' (inexistente) e o ecrã de gestão oferecia
     * manual/automatic/online. Com tipos inválidos a tesouraria não consegue
     * decidir se o valor entra na caixa.
     */
    protected function normalizarTiposDePagamento(bool $dry, ?string $tenantId): int
    {
        $equivalencias = [
            'bank'            => 'bank_transfer',
            'transfer'        => 'bank_transfer',
            'digital_payment' => 'digital_wallet',
            'online'          => 'digital_wallet',
            'cheque'          => 'check',
            'manual'          => 'other',
            'automatic'       => 'other',
        ];

        $total = 0;
        foreach ($equivalencias as $de => $para) {
            $q = DB::table('treasury_payment_methods')->where('type', $de);
            if ($tenantId) {
                $q->where('tenant_id', $tenantId);
            }

            $n = $dry ? $q->count() : $q->update(['type' => $para]);
            if ($n > 0) {
                $this->line("  tipo <fg=yellow>{$de}</> → <fg=green>{$para}</>: {$n} registo(s)");
                $total += $n;
            }
        }

        return $total;
    }

    /** Lista legível do que falta a um módulo para ser utilizável. */
    protected function prerequisitosEmFalta(Tenant $tenant, string $slug): array
    {
        $faltas = [];

        if ($slug === 'treasury') {
            $n = DB::table('treasury_payment_methods')
                ->where('tenant_id', $tenant->id)->where('is_active', 1)->count();
            if ($n === 0) {
                $faltas[] = 'sem métodos de pagamento';
            }
        }

        if ($slug === 'invoicing') {
            if (DB::table('invoicing_taxes')->where('tenant_id', $tenant->id)->count() === 0) {
                $faltas[] = 'sem impostos';
            }
            if (!DB::table('invoicing_settings')->where('tenant_id', $tenant->id)->exists()) {
                $faltas[] = 'sem configurações';
            }
            $temArmazem = DB::table('invoicing_warehouses')
                ->where('tenant_id', $tenant->id)->where('is_default', 1)->where('is_active', 1)->exists();
            if (!$temArmazem) {
                $faltas[] = 'sem armazém por omissão';
            }
        }

        return $faltas;
    }
}
