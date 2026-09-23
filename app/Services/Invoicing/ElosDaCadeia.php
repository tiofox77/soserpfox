<?php

namespace App\Services\Invoicing;

use Illuminate\Database\Eloquent\Builder as ConsultaEloquent;
use Illuminate\Database\Query\Builder as Consulta;

/**
 * O DOCUMENTO ANTERIOR DA CADEIA DE ASSINATURAS, como está gravado.
 *
 * Cada documento fiscal assina-se com o hash do anterior. A leitura desse
 * anterior acontece dentro da transacção da emissão, e no MySQL (REPEATABLE
 * READ) um SELECT simples lê a FOTOGRAFIA tirada na primeira leitura da
 * transacção, e não o que está gravado.
 *
 * Duas vendas ao mesmo segundo: a segunda espera pelo bloqueio da série e
 * recebe o número seguinte (esse é lido sob bloqueio), mas lê o anterior da
 * fotografia antiga, onde a primeira ainda não existe. Assina-se então com o
 * mesmo anterior da primeira e a cadeia bifurca. A Luk Simões tinha 25 destas
 * na série FR4226S61319N quando se deu por isso (FR 003253/003254, 23/09/2026).
 *
 * O FOR UPDATE lê o que está gravado e faz esperar por um documento que outra
 * emissão tenha inserido e ainda não confirmado. Uma porta só para a regra,
 * porque são cinco as emissões que a precisam: POS, facturas, notas, compras
 * e módulos.
 */
final class ElosDaCadeia
{
    public static function trancar(ConsultaEloquent|Consulta $consulta): ConsultaEloquent|Consulta
    {
        return $consulta->lockForUpdate();
    }
}
