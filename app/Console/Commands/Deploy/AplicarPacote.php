<?php

namespace App\Console\Commands\Deploy;

use App\Support\Deploy\Pacote;
use Illuminate\Console\Command;
use Throwable;

/**
 * APLICA UM PACOTE sobre o instantâneo que foi tirado para ele.
 *
 *   deploy:aplicar soserp-2026.09.13.1.zip --instantaneo=20260913-181500
 *
 * Recusa sem instantâneo, com um instantâneo de outro pacote, ou se algum
 * ficheiro mudou depois de o tirar. Corrido pela rota de manutenção, o
 * OPcache reposto no fim é o da web.
 */
class AplicarPacote extends Command
{
    use PastasDoDeploy;

    protected $signature = 'deploy:aplicar
        {pacote : o zip que está em storage/app/deploy/pacotes}
        {--instantaneo= : o instantâneo tirado para este pacote}';

    protected $description = 'Aplica um pacote de deploy (só depois de deploy:instantaneo)';

    public function handle(): int
    {
        @set_time_limit(0);

        if (! $this->option('instantaneo')) {
            $this->error('Falta --instantaneo. Primeiro: deploy:instantaneo ' . $this->argument('pacote'));

            return self::FAILURE;
        }

        try {
            $pacote = $this->caminhoDoPacote((string) $this->argument('pacote'));
            $pasta = $this->pastaDoInstantaneo((string) $this->option('instantaneo'));

            $resultado = Pacote::aplicar(base_path(), $pacote, $pasta);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('Se chegou a escrever alguma coisa: deploy:restaurar ' . $this->option('instantaneo') . ' --confirmar');

            return self::FAILURE;
        }

        $this->info("Aplicado: {$resultado['escritos']} ficheiros escritos, {$resultado['apagados']} apagados.");
        $this->line('A seguir: migrate, optimize:clear, config:cache, route:cache, view:cache, deploy:opcache-reset.');
        $this->line('Para voltar atrás: deploy:restaurar ' . $this->option('instantaneo') . ' --confirmar');

        return self::SUCCESS;
    }
}
