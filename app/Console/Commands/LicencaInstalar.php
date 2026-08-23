<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseState;
use App\Services\Licensing\MachineFingerprint;
use Illuminate\Console\Command;

/**
 * Instala uma licença a partir de um ficheiro — a activação silenciosa que o
 * instalador .exe chama no fim da configuração. Verifica antes de gravar.
 */
class LicencaInstalar extends Command
{
    protected $signature = 'licenca:instalar {ficheiro : caminho do ficheiro .key}
        {--forcar : instala mesmo que o estado não seja ATIVA (mas nunca se INVÁLIDA)}';

    protected $description = 'Instala (e verifica) uma licença a partir de um ficheiro';

    public function handle(LicenseManager $licencas): int
    {
        $ficheiro = $this->argument('ficheiro');
        if (!is_file($ficheiro)) {
            $this->error("Ficheiro não encontrado: {$ficheiro}");

            return self::FAILURE;
        }

        $token = trim((string) file_get_contents($ficheiro));
        $estado = $licencas->verificarToken($token, [
            'fingerprint' => MachineFingerprint::atual(),
        ]);

        if ($estado->estado === LicenseState::INVALIDA) {
            $this->error('Licença recusada: ' . $estado->motivo);

            return self::FAILURE;
        }

        if (!$estado->valida && !$this->option('forcar')) {
            $this->error('Licença não activa (' . $estado->estado . '): ' . $estado->motivo);
            $this->line('Use --forcar para instalar mesmo assim.');

            return self::FAILURE;
        }

        $licencas->store()->guardarToken($token);
        $this->info('Licença instalada. Estado: ' . $estado->estado . '.');
        if ($estado->payload) {
            $this->line('Empresa: ' . ($estado->payload->empresa() ?? '—')
                . ' · Plano: ' . ($estado->payload->plano() ?? '—'));
        }

        return self::SUCCESS;
    }
}
