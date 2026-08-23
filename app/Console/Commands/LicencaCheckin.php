<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseCheckin;
use Illuminate\Console\Command;

/**
 * Força um check-in ao servidor de licenças (o phone-home). Corre à mão, por
 * agendador, ou à boleia do tráfego (middleware). Só faz algo na build offline
 * com `checkin_url` definido.
 */
class LicencaCheckin extends Command
{
    protected $signature = 'licenca:checkin';

    protected $description = 'Liga ao servidor de licenças (phone-home) e aplica o resultado';

    public function handle(): int
    {
        $r = LicenseCheckin::apartirDaConfig()->executar();

        if (($r['ok'] ?? false) === true) {
            $this->info('Check-in: ' . ($r['acao'] ?? 'ok'));
            foreach ($r['notificacoes'] ?? [] as $n) {
                $this->line('  • ' . (is_string($n) ? $n : json_encode($n, JSON_UNESCAPED_UNICODE)));
            }

            return self::SUCCESS;
        }

        $this->warn('Check-in sem alteração: ' . ($r['motivo'] ?? 'desconhecido'));

        return self::SUCCESS; // não é erro operacional (offline é normal)
    }
}
