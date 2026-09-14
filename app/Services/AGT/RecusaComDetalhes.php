<?php

namespace App\Services\AGT;

use DomainException;

/**
 * Uma acção da AGT recusada com mais do que uma frase.
 *
 * Activar produção sem estar pronto, trocar de ambiente sem confirmar, apagar
 * as chaves que estão a assinar: o ecrã precisa de saber O QUÊ — a lista do
 * que falta, quantas submissões ficam à espera, que é uma confirmação que se
 * pede — para desenhar o aviso certo em vez de um recado genérico. Os
 * `detalhes` seguem tal e qual na resposta 422, ao lado da `message`.
 */
class RecusaComDetalhes extends DomainException
{
    /** @param array<string, mixed> $detalhes */
    public function __construct(string $mensagem, public readonly array $detalhes = [])
    {
        parent::__construct($mensagem);
    }
}
