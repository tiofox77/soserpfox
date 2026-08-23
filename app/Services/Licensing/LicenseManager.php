<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;

/**
 * A porta única para o resto da aplicação: "em que estado está a licença?".
 *
 * Junta o LicenseStore (persistência) ao LicenseVerifier (regras) e devolve um
 * LicenseState. É o que um middleware, um banner ou o comando `licenca:ver`
 * chamam. Cacheia por processo para não reler o ficheiro a cada uso.
 *
 * NÃO está ligado a nenhum enforcement — chamar isto não tranca nada. A
 * ligação (middleware/banner) faz-se por fases, ver o PRD de licenciamento.
 */
class LicenseManager
{
    private ?LicenseState $cache = null;

    public function __construct(
        private LicenseStore $store,
        private array $cfg,
    ) {
    }

    /** Constrói a partir da config (atalho para uso fora do container). */
    public static function apartirDaConfig(): self
    {
        $cfg = config('licensing', []);

        return new self(new LicenseStore($cfg), $cfg);
    }

    public function estado(bool $fresco = false): LicenseState
    {
        if ($this->cache !== null && !$fresco) {
            return $this->cache;
        }

        $token = $this->store->token();
        if (!$token) {
            return $this->cache = LicenseState::invalida('Nenhuma licença instalada.');
        }

        $pub = $this->cfg['public_key'] ?? '';
        if (!$pub) {
            return $this->cache = LicenseState::invalida('Chave pública do emissor em falta (LICENSE_PUBLIC_KEY).');
        }

        $estadoLocal = $this->store->estado();
        $agora = CarbonImmutable::now();

        $resultado = (new LicenseVerifier($pub, $this->cfg))->verificar($token, [
            'agora'           => $agora,
            'fingerprint'     => MachineFingerprint::atual(),
            'ultimo_checkin'  => $estadoLocal['ultimo_checkin'] ?? null,
            'relogio_max'     => $estadoLocal['relogio_max'] ?? null,
            'remote_bloqueio' => $estadoLocal['remote_bloqueio'] ?? null,
        ]);

        // Sobe a fasquia do relógio DEPOIS de verificar (a verificação usou o
        // valor anterior). Se o relógio recuou, isto mantém o máximo antigo.
        try {
            $this->store->tocarRelogio($agora);
        } catch (\Throwable $e) {
            // persistência de estado nunca deve rebentar a verificação
        }

        return $this->cache = $resultado;
    }

    /** Verifica um token avulso (sem passar pelo ficheiro instalado). */
    public function verificarToken(string $token, array $contexto = []): LicenseState
    {
        $pub = $this->cfg['public_key'] ?? '';
        if (!$pub) {
            return LicenseState::invalida('Chave pública do emissor em falta (LICENSE_PUBLIC_KEY).');
        }

        return (new LicenseVerifier($pub, $this->cfg))->verificar($token, $contexto);
    }

    public function store(): LicenseStore
    {
        return $this->store;
    }
}
