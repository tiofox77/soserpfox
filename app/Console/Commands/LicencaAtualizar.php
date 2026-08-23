<?php

namespace App\Console\Commands;

use App\Services\Licensing\UpdateService;
use Illuminate\Console\Command;

/**
 * Verifica (e opcionalmente aplica) uma atualização — lado CLIENTE. Sem
 * `--aplicar` só mostra o que há; com ele, aplica com backup + rollback.
 */
class LicencaAtualizar extends Command
{
    protected $signature = 'licenca:atualizar {--aplicar : aplica a atualização encontrada}';

    protected $description = 'Verifica e (opcional) aplica uma atualização do soserp';

    public function handle(): int
    {
        $servico = UpdateService::apartirDaConfig();

        $claims = $servico->verificar();
        if (!$claims) {
            $this->info('Sem atualizações (ou sem servidor configurado).');

            return self::SUCCESS;
        }

        $this->info("Atualização disponível: versão {$claims['versao']}"
            . (!empty($claims['obrigatorio']) ? ' (OBRIGATÓRIA)' : ''));
        if (!empty($claims['notas'])) {
            $this->line($claims['notas']);
        }

        if (!$this->option('aplicar')) {
            $this->line('Corra com --aplicar para instalar (faz backup da BD antes).');

            return self::SUCCESS;
        }

        $this->warn('A aplicar... (backup da BD, migração, health-check)');
        $r = $servico->aplicar($claims);

        if ($r['ok']) {
            $this->info("Atualizado para {$claims['versao']}. Backup: " . ($r['backup'] ?? '—'));

            return self::SUCCESS;
        }

        $this->error("Falha na etapa '{$r['etapa']}': " . ($r['motivo'] ?? 'desconhecido'));
        if (!empty($r['backup'])) {
            $this->line('BD restaurada do backup: ' . $r['backup']);
        }

        return self::FAILURE;
    }
}
