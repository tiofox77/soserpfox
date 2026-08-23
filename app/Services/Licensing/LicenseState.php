<?php

namespace App\Services\Licensing;

/**
 * O veredicto da verificação de uma licença, num objecto só de leitura.
 *
 * É o que a aplicação lê para decidir o que mostrar e o que travar. Fecha
 * SEMPRE por defeito: qualquer dúvida (assinatura, formato, relógio) cai em
 * INVALIDA/BLOQUEADA, nunca em ATIVA.
 */
class LicenseState
{
    public const ATIVA      = 'ativa';       // tudo bem
    public const AVISO      = 'aviso';        // válida, mas convém ligar à net
    public const BANNER     = 'banner';       // aviso permanente no ecrã
    public const SO_LEITURA = 'so_leitura';   // deixa consultar, trava a escrita
    public const BLOQUEADA  = 'bloqueada';    // trava tudo (offline demais / expirada)
    public const INVALIDA   = 'invalida';     // adulterada / ausente / outra máquina

    public function __construct(
        public readonly string $estado,
        public readonly bool $valida,
        public readonly string $motivo,
        public readonly ?int $diasParaExpirar = null,
        public readonly ?int $diasOffline = null,
        public readonly ?int $gracaDias = null,
        public readonly ?LicensePayload $payload = null,
    ) {
    }

    public static function invalida(string $motivo): self
    {
        return new self(self::INVALIDA, false, $motivo);
    }

    /** A escrita (emitir, guardar, alterar) deve ser recusada? */
    public function bloqueiaEscrita(): bool
    {
        return in_array($this->estado, [self::SO_LEITURA, self::BLOQUEADA, self::INVALIDA], true);
    }

    /** O sistema deve estar completamente trancado? */
    public function bloqueiaTudo(): bool
    {
        return in_array($this->estado, [self::BLOQUEADA, self::INVALIDA], true);
    }

    /** Há alguma coisa para mostrar ao utilizador (aviso/banner)? */
    public function mostraAviso(): bool
    {
        return $this->estado !== self::ATIVA;
    }

    public function toArray(): array
    {
        return [
            'estado'            => $this->estado,
            'valida'            => $this->valida,
            'motivo'            => $this->motivo,
            'bloqueia_escrita'  => $this->bloqueiaEscrita(),
            'bloqueia_tudo'     => $this->bloqueiaTudo(),
            'dias_para_expirar' => $this->diasParaExpirar,
            'dias_offline'      => $this->diasOffline,
            'graca_dias'        => $this->gracaDias,
            'empresa'           => $this->payload?->empresa(),
            'tenant_id'         => $this->payload?->tenantId(),
            'plano'             => $this->payload?->plano(),
            'modulos'           => $this->payload?->modulos(),
        ];
    }
}
