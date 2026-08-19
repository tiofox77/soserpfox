<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;

/**
 * Liga a activação automática nos planos que anunciam período de teste.
 *
 * Regra de negócio: o teste que o plano anuncia é o teste que o cliente
 * recebe — entra e trabalha, sem esperar por aprovação e sem comprovativo
 * (não há nada a pagar durante o teste).
 *
 * O que NÃO muda: a cortesia continua a ser UMA por cliente. O
 * DireitoACortesia trava quem já a gastou (mesmo NIF, mesmo utilizador,
 * mesmo empresas apagadas), por isso isto não abre a porta a repetições.
 */
class PlanosTrialAutomatico extends Command
{
    protected $signature = 'planos:trial-automatico
        {--desligar : Faz o contrário — volta a exigir aprovação}
        {--so-ver : Mostra o que mudaria, sem gravar}';

    protected $description = 'Activa automaticamente o período de teste nos planos que o têm';

    public function handle(): int
    {
        $ligar = !$this->option('desligar');
        $soVer = $this->option('so-ver');

        $planos = Plan::where('trial_days', '>', 0)->orderBy('order')->get();

        if ($planos->isEmpty()) {
            $this->warn('Nenhum plano com período de teste.');
            return self::SUCCESS;
        }

        $linhas = [];
        $mudados = 0;

        foreach ($planos as $plano) {
            $antes = (bool) $plano->auto_activate;

            if ($antes !== $ligar) {
                if (!$soVer) {
                    $plano->auto_activate = $ligar;
                    $plano->save();
                }
                $mudados++;
                $estado = $antes ? 'sim → NÃO' : 'não → SIM';
            } else {
                $estado = $antes ? 'já sim' : 'já não';
            }

            $linhas[] = [
                $plano->name,
                $plano->trial_days . ' dias',
                number_format((float) $plano->getPrice('monthly'), 2) . ' Kz',
                $estado,
            ];
        }

        $this->table(['plano', 'teste', 'mensal', 'activação automática'], $linhas);

        if ($soVer) {
            $this->info("(só ver) {$mudados} plano(s) mudariam.");
            return self::SUCCESS;
        }

        $this->info($ligar
            ? "{$mudados} plano(s) passam a activar o teste automaticamente."
            : "{$mudados} plano(s) voltam a exigir aprovação.");

        return self::SUCCESS;
    }
}
