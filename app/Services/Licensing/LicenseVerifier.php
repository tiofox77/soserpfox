<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;

/**
 * O CORAÇÃO offline: dado um token e o contexto local, diz se a licença vale
 * e em que estado está — SEM tocar na internet.
 *
 * É uma função pura: recebe tudo por parâmetro (agora, fingerprint, último
 * check-in, relógio-máximo) e não escreve nada. Quem persiste é o LicenseStore.
 * Isto torna cada regra testável isoladamente.
 *
 * Ordem das verificações (todas falham FECHADO):
 *   1. formato do token          → INVALIDA
 *   2. assinatura Ed25519        → INVALIDA (adulterada / outro emissor)
 *   3. versão do esquema         → INVALIDA
 *   4. ainda não entrou em vigor → INVALIDA
 *   5. máquina errada (fp)       → INVALIDA
 *   6. relógio recuado           → BLOQUEADA (batota de data)
 *   7. subscrição expirada       → BLOQUEADA
 *   8. tempo offline             → ATIVA → AVISO → BANNER → SO_LEITURA → BLOQUEADA
 */
class LicenseVerifier
{
    public function __construct(
        private string $chavePublicaB64,
        private array $cfg = [],
    ) {
    }

    /**
     * @param array $contexto {
     *   agora?: CarbonImmutable, fingerprint?: string,
     *   ultimo_checkin?: CarbonImmutable|string|null,
     *   relogio_max?: CarbonImmutable|string|null
     * }
     */
    public function verificar(string $token, array $contexto = []): LicenseState
    {
        $agora = $this->comoData($contexto['agora'] ?? null) ?? CarbonImmutable::now();

        // 1. formato
        try {
            [$payloadB64, $sigB64] = $this->partir($token);
        } catch (\Throwable $e) {
            return LicenseState::invalida('Formato de licença inválido.');
        }

        // 2. assinatura
        if (!$this->assinaturaOk($payloadB64, $sigB64)) {
            return LicenseState::invalida('Assinatura inválida — licença adulterada ou de outro emissor.');
        }

        // conteúdo
        $claims = json_decode(Base64Url::decode($payloadB64), true);
        if (!is_array($claims)) {
            return LicenseState::invalida('Conteúdo da licença ilegível.');
        }
        $p = new LicensePayload($claims);

        // 3. versão
        if ((int) ($claims['v'] ?? 0) !== 1) {
            return LicenseState::invalida('Versão de licença não suportada.');
        }

        // 4. não-antes
        if (($nbf = $p->validaDe()) && $agora->lt($nbf)) {
            return LicenseState::invalida('Licença ainda não entrou em vigor.');
        }

        // 5. fingerprint da máquina
        if (($this->cfg['bind_machine'] ?? true) && $p->fingerprint()) {
            $fp = $contexto['fingerprint'] ?? MachineFingerprint::atual();
            if (!hash_equals($p->fingerprint(), (string) $fp)) {
                return LicenseState::invalida('Licença emitida para outra máquina.');
            }
        }

        // 5b. bloqueio remoto (o servidor de licenças mandou suspender no último
        // check-in). Só se limpa quando chegar uma renovação assinada válida.
        if (!empty($contexto['remote_bloqueio'])) {
            return new LicenseState(
                LicenseState::BLOQUEADA, false,
                'Bloqueada pelo fornecedor: ' . $contexto['remote_bloqueio'],
                null, null, null, $p
            );
        }

        $expira = $p->expiraEm();
        $diasExp = $expira ? (int) floor($agora->diffInSeconds($expira, false) / 86400) : null;

        // 6. relógio recuado (não deixa ganhar dias de graça a mexer na data)
        $tol = (int) ($this->cfg['clock_skew_tolerance'] ?? 120);
        if ($relogioMax = $this->comoData($contexto['relogio_max'] ?? null)) {
            if ($agora->lt($relogioMax->subSeconds($tol))) {
                return new LicenseState(
                    LicenseState::BLOQUEADA, false,
                    'Relógio do sistema recuado — verificação suspensa até acertar a data.',
                    $diasExp, null, null, $p
                );
            }
        }

        // 7. expiração da subscrição
        if ($expira && $agora->gte($expira)) {
            return new LicenseState(
                LicenseState::BLOQUEADA, false, 'Subscrição expirada.',
                $diasExp, null, null, $p
            );
        }

        // 8. tempo offline (sem "ligar a casa")
        $graca = $p->gracaDias() ?? (int) ($this->cfg['offline_grace_days'] ?? 15);
        $baseline = $this->comoData($contexto['ultimo_checkin'] ?? null)
            ?? $p->emitidaEm()
            ?? $agora;
        $diasOffline = max(0, (int) floor($baseline->diffInSeconds($agora, false) / 86400));

        if ($graca > 0 && $diasOffline >= $graca) {
            return new LicenseState(
                LicenseState::BLOQUEADA, false,
                "Sem ligar à internet há {$diasOffline} dias (limite {$graca}).",
                $diasExp, $diasOffline, $graca, $p
            );
        }

        // escada de degradação
        $ratio = $graca > 0 ? $diasOffline / $graca : 0.0;
        $esc = $this->cfg['degradacao'] ?? [];
        $estado = LicenseState::ATIVA;
        $motivo = 'Licença válida.';

        if ($ratio >= ($esc['so_leitura'] ?? 0.95)) {
            $estado = LicenseState::SO_LEITURA;
            $motivo = "Modo só-leitura: ligue à internet ({$diasOffline}/{$graca} dias offline).";
        } elseif ($ratio >= ($esc['banner'] ?? 0.8)) {
            $estado = LicenseState::BANNER;
            $motivo = "Ligue à internet em breve ({$diasOffline}/{$graca} dias offline).";
        } elseif ($ratio >= ($esc['aviso'] ?? 0.5)) {
            $estado = LicenseState::AVISO;
            $motivo = "Sem ligar à internet há {$diasOffline} dias.";
        }

        return new LicenseState($estado, true, $motivo, $diasExp, $diasOffline, $graca, $p);
    }

    /**
     * Lê o payload de um token SÓ se a assinatura for válida — sem olhar a
     * vigência, máquina ou relógio. É o que o SERVIDOR de licenças usa no
     * check-in: quer confiar em QUEM é o token (o tenant), e decide o resto
     * pela subscrição na base, não pelo estado local do cliente.
     */
    public function payloadAssinado(string $token): ?LicensePayload
    {
        try {
            [$payloadB64, $sigB64] = $this->partir($token);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$this->assinaturaOk($payloadB64, $sigB64)) {
            return null;
        }

        $claims = json_decode(Base64Url::decode($payloadB64), true);

        return is_array($claims) ? new LicensePayload($claims) : null;
    }

    /** @return array{0:string,1:string} [payloadB64, sigB64] */
    private function partir(string $token): array
    {
        $partes = explode('.', trim($token));

        if (count($partes) !== 4) {
            throw new \InvalidArgumentException('número de partes');
        }

        [$prefixo, $versao, $payload, $sig] = $partes;

        if ($prefixo !== LicenseIssuer::PREFIXO || $versao !== LicenseIssuer::VERSAO) {
            throw new \InvalidArgumentException('prefixo/versão');
        }

        return [$payload, $sig];
    }

    private function assinaturaOk(string $payloadB64, string $sigB64): bool
    {
        $pub = base64_decode($this->chavePublicaB64, true);
        if ($pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        $sig = Base64Url::decode($sigB64);
        if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($sig, $payloadB64, $pub);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function comoData($valor): ?CarbonImmutable
    {
        if ($valor instanceof CarbonImmutable) {
            return $valor;
        }
        if (empty($valor)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
