<?php

namespace App\Exceptions\HR;

use App\Models\HR\Payroll;
use RuntimeException;

/**
 * Já existe folha de pagamento para aquele mês naquela empresa.
 *
 * Existe para o ecrã poder dizer isto por palavras — e apontar a folha que
 * já lá está — em vez de mostrar ao utilizador o erro cru do índice único
 * da base de dados.
 */
class FolhaJaExiste extends RuntimeException
{
    public function __construct(string $message, public readonly ?Payroll $folha = null)
    {
        parent::__construct($message);
    }
}
