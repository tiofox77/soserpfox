<?php

namespace App\Console\Commands\Deploy;

use App\Support\Deploy\Pacote;
use Illuminate\Console\Command;
use Throwable;

/**
 * O BACKUP DE ANTES DO DEPLOY — os ficheiros que o pacote vai tocar e a base
 * de dados inteira, numa pasta com a data. Não muda nada na produção.
 *
 *   deploy:instantaneo soserp-2026.09.13.1.zip
 *
 * O nome que devolve (20260913-181500) é o que o `deploy:aplicar` e o
 * `deploy:restaurar` pedem.
 */
class TirarInstantaneo extends Command
{
    use PastasDoDeploy;

    protected $signature = 'deploy:instantaneo
        {pacote : o zip que está em storage/app/deploy/pacotes}
        {--sem-bd : só os ficheiros, sem a cópia da base de dados}';

    protected $description = 'Guarda os ficheiros que um pacote vai tocar e a base de dados, antes de o aplicar';

    public function handle(): int
    {
        @set_time_limit(0);

        try {
            $pacote = $this->caminhoDoPacote((string) $this->argument('pacote'));
            $nome = date('Ymd-His');
            $pasta = $this->pastaDoInstantaneo($nome);

            $manifesto = Pacote::instantaneo(base_path(), $pacote, $pasta);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Instantâneo {$nome}");
        $this->line('  ficheiros guardados: ' . count($manifesto['guardados']));
        $this->line('  ficheiros que o pacote cria: ' . count($manifesto['criados']));
        $this->line('  ficheiros que o pacote apaga: ' . count($manifesto['apagar']));
        $this->line('  codigo.zip: ' . round(filesize($pasta . '/codigo.zip') / 1048576, 2) . ' MB');

        if (! $this->option('sem-bd')) {
            $codigo = $this->call('db:dump', ['--destino' => $pasta . '/bd.sql.gz']);

            if ($codigo !== self::SUCCESS) {
                $this->error('A cópia da base de dados FALHOU — não aplique o pacote sem ela.');

                return self::FAILURE;
            }
        }

        $this->info("Pronto. Aplicar: deploy:aplicar {$this->argument('pacote')} --instantaneo={$nome}");

        return self::SUCCESS;
    }
}
