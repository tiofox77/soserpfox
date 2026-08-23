<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * A persistência local, na máquina do cliente: onde está a licença e o estado
 * (último check-in + relógio-máximo já visto).
 *
 * O relógio-máximo é a defesa contra recuar a data do PC para ganhar dias de
 * graça: guarda-se sempre o MAIOR instante já observado; se o relógio andar
 * para trás, o LicenseVerifier apanha-o.
 */
class LicenseStore
{
    public function __construct(private array $cfg)
    {
    }

    /** O token da licença — de ficheiro, ou inline no .env. */
    public function token(): ?string
    {
        $path = $this->cfg['license_path'] ?? null;

        if (is_string($path) && is_file($path)) {
            $conteudo = trim((string) @file_get_contents($path));
            if ($conteudo !== '') {
                return $conteudo;
            }
        }

        $inline = $this->cfg['inline_token'] ?? null;

        return is_string($inline) && trim($inline) !== '' ? trim($inline) : null;
    }

    /** Grava (ou substitui) o token da licença. */
    public function guardarToken(string $token): void
    {
        $path = $this->cfg['license_path'];
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, trim($token));
    }

    /** Estado local persistido (último check-in, relógio-máximo). */
    public function estado(): array
    {
        $path = $this->cfg['state_path'] ?? null;

        if (is_string($path) && is_file($path)) {
            $j = json_decode((string) @file_get_contents($path), true);
            if (is_array($j)) {
                return $j;
            }
        }

        return [];
    }

    public function guardarEstado(array $estado): void
    {
        $path = $this->cfg['state_path'];
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Regista um check-in bem-sucedido ("liguei a casa e correu bem"). É isto
     * que faz recuar o contador de dias offline.
     */
    public function registarCheckin(?CarbonImmutable $quando = null): void
    {
        $quando = $quando ?? CarbonImmutable::now();
        $estado = $this->estado();
        $estado['ultimo_checkin'] = $quando->toIso8601String();
        $estado['relogio_max']    = $this->maiorRelogio($quando)->toIso8601String();
        $this->guardarEstado($estado);
    }

    /**
     * Sobe a fasquia do relógio-máximo (nunca desce). Chamar a cada leitura
     * de estado — é o que permite detectar um recuo mais tarde.
     */
    public function tocarRelogio(?CarbonImmutable $agora = null): void
    {
        $agora = $agora ?? CarbonImmutable::now();
        $estado = $this->estado();
        $estado['relogio_max'] = $this->maiorRelogio($agora)->toIso8601String();
        $this->guardarEstado($estado);
    }

    private function maiorRelogio(CarbonImmutable $agora): CarbonImmutable
    {
        $estado = $this->estado();
        if (!empty($estado['relogio_max'])) {
            try {
                $anterior = CarbonImmutable::parse($estado['relogio_max']);
                if ($anterior->gt($agora)) {
                    return $anterior;
                }
            } catch (\Throwable $e) {
                // ignora um relógio-máximo corrompido
            }
        }

        return $agora;
    }
}
