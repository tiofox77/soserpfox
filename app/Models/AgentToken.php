<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial do agente externo.
 *
 * O segredo NUNCA é guardado em claro e não há maneira de o recuperar:
 * mostra-se uma vez na emissão e acabou. O prefixo, esse, fica em claro
 * porque serve só para encontrar a linha antes de comparar o hash.
 */
class AgentToken extends Model
{
    protected $fillable = [
        'name', 'prefix', 'token_hash', 'owner_user_id', 'scopes',
        'allowed_ips', 'expires_at', 'last_used_at', 'last_ip',
        'revoked_at', 'revoked_by', 'revoked_reason', 'created_by',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'allowed_ips'  => 'array',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** O segredo guarda-se assim; comparar sempre com hash_equals. */
    public static function hashSegredo(string $segredo): string
    {
        return hash('sha256', $segredo);
    }

    public function estaRevogado(): bool
    {
        return $this->revoked_at !== null;
    }

    public function estaExpirado(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function estaUtilizavel(): bool
    {
        return !$this->estaRevogado() && !$this->estaExpirado();
    }

    public function temEscopo(string $escopo): bool
    {
        return in_array($escopo, $this->scopes ?? [], true);
    }

    /**
     * Um IP autorizado. Sem lista configurada, e se a configuração exigir
     * lista, NADA passa — a falha é fechada, não aberta.
     */
    public function aceitaIp(?string $ip): bool
    {
        $lista = $this->allowed_ips ?? [];

        if (empty($lista)) {
            return !config('agent.token.exigir_ips', true);
        }

        return in_array($ip, $lista, true);
    }
}
