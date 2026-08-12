<?php

namespace App\Console\Commands;

use App\Models\HR\HRSetting;
use App\Services\HR\DefinicoesRH;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Garante que cada empresa com RH tem as suas definições.
 *
 * Serve dois momentos: repor as empresas que nunca as tiveram (o seeder estava
 * fixo à empresa 1) e distribuir definições novas quando o catálogo cresce.
 *
 * Só ACRESCENTA o que falta. Não altera valores existentes, e é por isso que
 * pode correr-se em produção sem medo: uma empresa que baixou o INSS ou mudou o
 * subsídio de alimentação não vê isso revertido.
 */
class HrSettingsSyncCommand extends Command
{
    protected $signature = 'hr:settings-sync
                            {--tenant= : Só esta empresa}
                            {--dry-run : Mostra o que faria, sem escrever}';

    protected $description = 'Cria as definições de RH em falta nas empresas com o módulo activo';

    public function handle(): int
    {
        $empresas = $this->empresas();

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa com o módulo de RH activo.');

            return self::SUCCESS;
        }

        $catalogo = count(DefinicoesRH::catalogo());
        $seco     = (bool) $this->option('dry-run');

        $this->line("Catálogo: {$catalogo} definições" . ($seco ? '  (simulação)' : ''));
        $this->newLine();

        $total = 0;

        foreach ($empresas as $tenantId) {
            $tem = HRSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
            $faltam = $catalogo - $tem;

            if ($seco) {
                $this->line(sprintf('  empresa %-5s tem %2d, faltam %2d', $tenantId, $tem, max(0, $faltam)));
                $total += max(0, $faltam);

                continue;
            }

            $criadas = DefinicoesRH::garantirPara((int) $tenantId);
            $total  += $criadas;

            $this->line(sprintf(
                '  empresa %-5s %s',
                $tenantId,
                $criadas > 0 ? "+{$criadas}  (tinha {$tem})" : "já completa ({$tem})"
            ));
        }

        $this->newLine();
        $this->info($seco
            ? "Seriam criadas {$total} definição(ões)."
            : "✓ {$total} definição(ões) criada(s) em {$empresas->count()} empresa(s).");

        return self::SUCCESS;
    }

    /** As empresas a tratar: a indicada, ou todas as que têm o módulo de RH. */
    private function empresas()
    {
        if ($tenant = $this->option('tenant')) {
            return collect([(int) $tenant]);
        }

        return DB::table('tenant_module')
            ->join('modules', 'modules.id', '=', 'tenant_module.module_id')
            ->where('modules.slug', 'rh')
            ->distinct()
            ->orderBy('tenant_module.tenant_id')
            ->pluck('tenant_module.tenant_id');
    }
}
