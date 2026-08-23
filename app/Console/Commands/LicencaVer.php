<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseState;
use App\Services\Licensing\MachineFingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * VER a licença offline — o comando que responde "esta instalação está boa?".
 *
 * Sem argumentos, lê a licença instalada. Com --token/--ficheiro, inspecciona
 * um token avulso. Corre 100% offline: só cripto e o relógio local.
 */
class LicencaVer extends Command
{
    protected $signature = 'licenca:ver
        {--token= : verifica este token em vez do instalado}
        {--ficheiro= : lê o token deste ficheiro}
        {--json : imprime o estado em JSON}';

    protected $description = 'Verifica e mostra o estado da licença offline';

    public function handle(): int
    {
        $manager = LicenseManager::apartirDaConfig();

        $tokenAvulso = $this->option('token');
        if ($ficheiro = $this->option('ficheiro')) {
            if (!is_file($ficheiro)) {
                $this->error("Ficheiro não encontrado: {$ficheiro}");

                return self::FAILURE;
            }
            $tokenAvulso = trim((string) file_get_contents($ficheiro));
        }

        if ($tokenAvulso) {
            $estadoLocal = $manager->store()->estado();
            $estado = $manager->verificarToken($tokenAvulso, [
                'agora'          => CarbonImmutable::now(),
                'fingerprint'    => MachineFingerprint::atual(),
                'ultimo_checkin' => $estadoLocal['ultimo_checkin'] ?? null,
                'relogio_max'    => $estadoLocal['relogio_max'] ?? null,
            ]);
        } else {
            $estado = $manager->estado(true);
        }

        if ($this->option('json')) {
            $this->line(json_encode($estado->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $estado->valida ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('Máquina (fingerprint): <comment>' . MachineFingerprint::atual() . '</comment>');
        $this->newLine();

        $this->table(['Campo', 'Valor'], [
            ['Estado', $this->rotulo($estado->estado)],
            ['Válida', $estado->valida ? 'sim' : 'NÃO'],
            ['Motivo', $estado->motivo],
            ['Empresa', $estado->payload?->empresa() ?? '—'],
            ['Tenant', $estado->payload?->tenantId() ?? '—'],
            ['Plano', $estado->payload?->plano() ?? '—'],
            ['Módulos', implode(', ', $estado->payload?->modulos() ?? []) ?: '—'],
            ['Dias p/ expirar', $estado->diasParaExpirar ?? '—'],
            ['Dias offline', $estado->diasOffline ?? '—'],
            ['Graça (dias)', $estado->gracaDias ?? '—'],
            ['Bloqueia escrita', $estado->bloqueiaEscrita() ? 'SIM' : 'não'],
            ['Bloqueia tudo', $estado->bloqueiaTudo() ? 'SIM' : 'não'],
        ]);

        return $estado->valida ? self::SUCCESS : self::FAILURE;
    }

    private function rotulo(string $estado): string
    {
        return match ($estado) {
            LicenseState::ATIVA      => '<info>ATIVA</info>',
            LicenseState::AVISO      => '<comment>AVISO</comment>',
            LicenseState::BANNER     => '<comment>BANNER</comment>',
            LicenseState::SO_LEITURA => '<comment>SÓ-LEITURA</comment>',
            LicenseState::BLOQUEADA  => '<error>BLOQUEADA</error>',
            default                  => '<error>INVÁLIDA</error>',
        };
    }
}
