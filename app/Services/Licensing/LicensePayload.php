<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;

/**
 * O conteúdo assinado de uma licença — só leitura.
 *
 * São os factos que o emissor gravou e assinou: a quem pertence, que módulos
 * dá, até quando vale e a que máquina está presa. Nada aqui se recalcula; o
 * estado (activa/bloqueada) é obra do LicenseVerifier, não deste objecto.
 */
class LicensePayload
{
    public function __construct(public readonly array $claims)
    {
    }

    public function id(): ?string
    {
        return $this->claims['lic'] ?? null;
    }

    public function tenantId(): ?int
    {
        return isset($this->claims['tenant_id']) ? (int) $this->claims['tenant_id'] : null;
    }

    public function empresa(): ?string
    {
        return $this->claims['empresa'] ?? null;
    }

    public function nif(): ?string
    {
        return $this->claims['nif'] ?? null;
    }

    public function plano(): ?string
    {
        return $this->claims['plano'] ?? null;
    }

    /** @return string[] */
    public function modulos(): array
    {
        return is_array($this->claims['modulos'] ?? null) ? $this->claims['modulos'] : [];
    }

    public function temModulo(string $modulo): bool
    {
        $m = $this->modulos();

        return in_array('*', $m, true) || in_array($modulo, $m, true);
    }

    public function emitidaEm(): ?CarbonImmutable
    {
        return isset($this->claims['iat']) ? CarbonImmutable::createFromTimestamp((int) $this->claims['iat']) : null;
    }

    public function validaDe(): ?CarbonImmutable
    {
        return isset($this->claims['nbf']) ? CarbonImmutable::createFromTimestamp((int) $this->claims['nbf']) : null;
    }

    public function expiraEm(): ?CarbonImmutable
    {
        return isset($this->claims['exp']) ? CarbonImmutable::createFromTimestamp((int) $this->claims['exp']) : null;
    }

    public function fingerprint(): ?string
    {
        return $this->claims['fp'] ?? null;
    }

    public function gracaDias(): ?int
    {
        return isset($this->claims['graca']) ? (int) $this->claims['graca'] : null;
    }

    /** Tecto de utilizadores desta licença. 0/null = sem tecto. */
    public function maxUtilizadores(): int
    {
        return (int) ($this->claims['max_users'] ?? 0);
    }

    public function ambiente(): string
    {
        return $this->claims['env'] ?? 'prod';
    }

    public function toArray(): array
    {
        return $this->claims;
    }
}
