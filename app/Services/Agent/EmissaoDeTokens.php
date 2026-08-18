<?php

namespace App\Services\Agent;

use App\Models\AgentToken;
use App\Models\User;

/**
 * Emissão e revogação de credenciais do agente.
 *
 * Só corre a partir do painel do super admin — NÃO há endpoint para isto.
 * Um agente que possa emitir ou alargar as suas próprias credenciais não
 * tem credenciais nenhumas.
 */
class EmissaoDeTokens
{
    /**
     * Emite uma credencial.
     *
     * O valor em claro é devolvido UMA vez e nunca mais existe em lado
     * nenhum: guarda-se só o sha256 do segredo.
     *
     * @return array{token: AgentToken, em_claro: string}
     */
    public function emitir(
        string $nome,
        User $responsavel,
        array $escopos,
        array $ips,
        int $dias,
        ?User $criadoPor = null
    ): array {
        $validos = array_keys(config('agent.escopos', []));
        $pedidos = array_values(array_intersect($escopos, $validos));

        if (empty($pedidos)) {
            throw new \InvalidArgumentException('Uma credencial sem escopos não serve para nada.');
        }

        $maximo = (int) config('agent.token.validade_maxima', 90);
        if ($dias < 1 || $dias > $maximo) {
            throw new \InvalidArgumentException("A validade tem de estar entre 1 e {$maximo} dias.");
        }

        if (config('agent.token.exigir_ips', true) && empty($ips)) {
            throw new \InvalidArgumentException('Indique pelo menos um IP autorizado.');
        }

        $prefixo = bin2hex(random_bytes(6));
        $segredo = bin2hex(random_bytes(32));

        $token = AgentToken::create([
            'name'          => $nome,
            'prefix'        => $prefixo,
            'token_hash'    => AgentToken::hashSegredo($segredo),
            'owner_user_id' => $responsavel->id,
            'scopes'        => $pedidos,
            'allowed_ips'   => array_values($ips),
            'expires_at'    => now()->addDays($dias),
            'created_by'    => $criadoPor?->id,
        ]);

        return [
            'token'    => $token,
            'em_claro' => config('agent.token.prefixo', 'oclaw') . '_' . $prefixo . '_' . $segredo,
        ];
    }

    /** Revogação com efeito imediato: o middleware lê a linha a cada pedido. */
    public function revogar(AgentToken $token, ?User $porQuem, string $motivo): void
    {
        $token->update([
            'revoked_at'     => now(),
            'revoked_by'     => $porQuem?->id,
            'revoked_reason' => $motivo,
        ]);
    }
}
