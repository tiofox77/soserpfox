<?php

namespace App\Console\Commands;

use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use Illuminate\Console\Command;

/**
 * O rollout POR-TENANT que o super admin decide (a espinha da F4): abre uma
 * versão a um tenant específico (canary), a todos, ou fecha-a.
 *
 * O painel visual disto é a F5 — este comando é a mesma decisão por linha de
 * comando.
 */
class AtualizacaoAlvo extends Command
{
    protected $signature = 'atualizacao:alvo
        {--versao= : versão a controlar (ex.: 1.2.0)}
        {--tenant=* : id(s) de tenant a quem abrir a versão}
        {--rollout= : all (abrir a todos) ou none (fechar a todos)}
        {--remover : remove os tenants indicados em vez de os adicionar}';

    protected $description = 'Decide o rollout por-tenant de uma versão (canary/all/none)';

    public function handle(): int
    {
        $versao = $this->option('versao');
        if (!$versao) {
            $this->error('Falta --versao.');

            return self::FAILURE;
        }

        $update = AppUpdate::where('versao', $versao)->first();
        if (!$update) {
            $this->error("Versão {$versao} não publicada. Corra `atualizacao:publicar` primeiro.");

            return self::FAILURE;
        }

        if ($rollout = $this->option('rollout')) {
            if (!in_array($rollout, ['all', 'none'], true)) {
                $this->error('--rollout tem de ser all ou none.');

                return self::FAILURE;
            }
            $update->update(['rollout' => $rollout]);
            $this->info("Rollout de {$versao} definido para '{$rollout}'.");
        }

        $tenants = array_filter(array_map('intval', (array) $this->option('tenant')));
        foreach ($tenants as $tid) {
            if ($this->option('remover')) {
                AppUpdateTarget::where('tenant_id', $tid)->where('versao', $versao)->delete();
                $this->line("  - tenant {$tid} removido de {$versao}");
            } else {
                AppUpdateTarget::firstOrCreate(['tenant_id' => $tid, 'versao' => $versao]);
                $this->line("  + tenant {$tid} pode receber {$versao}");
            }
        }

        return self::SUCCESS;
    }
}
