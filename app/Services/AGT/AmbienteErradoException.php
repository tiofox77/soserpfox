<?php

namespace App\Services\AGT;

use DomainException;

/**
 * Pediu-se uma acção que ESCREVE na AGT enquanto se olhava para o ambiente
 * que não é o activo. Não é falta de configuração: é o ecrã a dizer
 * homologação e a empresa a emitir em produção — a acção não corre.
 */
class AmbienteErradoException extends DomainException
{
}
