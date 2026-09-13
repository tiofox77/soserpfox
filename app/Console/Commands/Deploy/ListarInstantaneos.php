<?php

namespace App\Console\Commands\Deploy;

use App\Support\Deploy\Pacote;
use Illuminate\Console\Command;
use Throwable;

/** Os instantâneos que há no servidor, do mais recente para o mais antigo. */
class ListarInstantaneos extends Command
{
    use PastasDoDeploy;

    protected $signature = 'deploy:instantaneos';

    protected $description = 'Lista os instantâneos de deploy (backups) e o estado de cada um';

    public function handle(): int
    {
        $pastas = glob($this->pastaDosInstantaneos() . '/*', GLOB_ONLYDIR) ?: [];
        rsort($pastas);

        if (! $pastas) {
            $this->line('Ainda não há instantâneos.');

            return self::SUCCESS;
        }

        $linhas = [];

        foreach ($pastas as $pasta) {
            try {
                $m = Pacote::manifesto($pasta);
            } catch (Throwable $e) {
                $linhas[] = [basename($pasta), '—', '—', '—', '—', $e->getMessage()];

                continue;
            }

            $bd = $pasta . '/bd.sql.gz';

            $linhas[] = [
                basename($pasta),
                $m['pacote'] ?? '—',
                count($m['guardados']) . ' / ' . count($m['criados']),
                is_file($bd) ? round(filesize($bd) / 1048576, 2) . ' MB' : 'sem cópia',
                $m['aplicado_em'] ?? '—',
                $m['restaurado_em'] ?? '—',
            ];
        }

        $this->table(['Instantâneo', 'Pacote', 'Guardados / criados', 'Base de dados', 'Aplicado', 'Restaurado'], $linhas);

        return self::SUCCESS;
    }
}
