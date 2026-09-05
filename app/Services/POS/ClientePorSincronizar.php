<?php

namespace App\Services\POS;

/**
 * O documento aponta para um cliente que o servidor ainda não tem.
 *
 * O aparelho mandou um `client_local_uuid` — um cliente criado sem rede — e
 * o servidor não encontra ninguém com esse identificador. Não é uma recusa:
 * é um ESTADO. O cliente vem na fila, antes ou depois deste documento, e a
 * sincronização seguinte resolve-o. Responde-se 409, que o aparelho não
 * trata como definitivo, e o documento fica à espera em vez de morrer.
 *
 * O que NÃO se faz: emitir em nome do Consumidor Final. Era o que
 * acontecia quando o cliente não se resolvia, e o documento fiscal saía em
 * nome de outra pessoa sem ninguém dar por nada.
 */
class ClientePorSincronizar extends \RuntimeException
{
    public function __construct(public readonly string $localUuid)
    {
        parent::__construct("O cliente {$localUuid} ainda não chegou ao servidor. Sincronize outra vez.");
    }
}
