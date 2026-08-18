<?php

namespace App\Support;

use App\Models\AgentToken;

/**
 * O agente do pedido em curso.
 *
 * Injectado no container pelo middleware. Não é um User e não tem sessão:
 * é deliberadamente uma coisa à parte, para nunca ser confundido com um
 * utilizador autenticado nem herdar as permissões de ninguém.
 */
class AgenteAutenticado
{
    public function __construct(
        public readonly AgentToken $token,
    ) {
    }

    public function id(): int
    {
        return $this->token->id;
    }

    public function nome(): string
    {
        return $this->token->name;
    }

    /** O humano que responde por este agente. */
    public function responsavelId(): int
    {
        return $this->token->owner_user_id;
    }

    public function escopos(): array
    {
        return $this->token->scopes ?? [];
    }

    public function pode(string $escopo): bool
    {
        return $this->token->temEscopo($escopo);
    }
}
