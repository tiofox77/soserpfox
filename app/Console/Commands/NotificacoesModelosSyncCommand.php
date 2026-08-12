<?php

namespace App\Console\Commands;

use App\Models\NotificationTemplate;
use App\Services\Notifications\ModelosPadrao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Garante que cada empresa com o módulo de notificações tem os modelos padrão.
 *
 * O ecrã cria-os na primeira visita, mas isso obriga alguém a lá ir por cada
 * empresa. Isto trata de todas de uma vez, e também distribui modelos novos
 * quando o catálogo crescer.
 *
 * Só ACRESCENTA o que falta. Nunca altera um modelo que a empresa tenha
 * reescrito — pode correr-se em produção sem medo.
 */
class NotificacoesModelosSyncCommand extends Command
{
    protected $signature = 'notifications:templates-sync
                            {--tenant= : Só esta empresa}
                            {--dry-run : Mostra o que faria, sem escrever}';

    protected $description = 'Cria os modelos de notificação em falta nas empresas com o módulo activo';

    public function handle(): int
    {
        $empresas = $this->empresas();

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa com o módulo de notificações activo.');

            return self::SUCCESS;
        }

        $catalogo = ModelosPadrao::catalogo();
        $seco     = (bool) $this->option('dry-run');

        // Quantos do catálogo conseguem de facto disparar hoje. Dizê-lo aqui
        // evita a impressão de que criar os modelos basta para haver avisos.
        $disparam = count(array_filter(
            $catalogo,
            fn ($m) => ModelosPadrao::dispara($m['module'] ?? null, $m['trigger_event'] ?? null)
        ));

        $this->line(sprintf(
            'Catálogo: %d modelos (%d disparam hoje, %d à espera de caminho)%s',
            count($catalogo),
            $disparam,
            count($catalogo) - $disparam,
            $seco ? '  (simulação)' : ''
        ));
        $this->newLine();

        $total = 0;

        foreach ($empresas as $tenantId) {
            $tem = NotificationTemplate::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

            if ($seco) {
                $faltam = max(0, count($catalogo) - $tem);
                $this->line(sprintf('  empresa %-5s tem %2d, faltam até %2d', $tenantId, $tem, $faltam));
                $total += $faltam;

                continue;
            }

            $criados = ModelosPadrao::garantirPara((int) $tenantId);
            $total  += $criados;

            $this->line(sprintf(
                '  empresa %-5s %s',
                $tenantId,
                $criados > 0 ? "+{$criados}  (tinha {$tem})" : "já completa ({$tem})"
            ));
        }

        $this->newLine();
        $this->info($seco
            ? "Seriam criados {$total} modelo(s)."
            : "✓ {$total} modelo(s) criado(s) em {$empresas->count()} empresa(s).");

        return self::SUCCESS;
    }

    private function empresas()
    {
        if ($tenant = $this->option('tenant')) {
            return collect([(int) $tenant]);
        }

        return DB::table('tenant_module')
            ->join('modules', 'modules.id', '=', 'tenant_module.module_id')
            ->where('modules.slug', 'notifications')
            ->distinct()
            ->orderBy('tenant_module.tenant_id')
            ->pluck('tenant_module.tenant_id');
    }
}
