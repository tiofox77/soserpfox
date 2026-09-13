<?php

namespace App\Support;

/**
 * UM TEXTO NUMA FOLHA DE CÁLCULO NÃO É UMA FÓRMULA.
 *
 * Os nomes de clientes e artigos vão para CSV e Excel — e alguns chegam da rua
 * (marcação do salão, reservas do hotel). Um nome começado por «=», «+», «-»,
 * «@» ou tabulação é lido pelo Excel como fórmula e corre no computador de quem
 * abre a exportação (auditoria de segurança de 2026-09-13). Antecede-se uma
 * plica, que o Excel esconde.
 */
final class CelulaSegura
{
    public static function texto(mixed $valor): mixed
    {
        if (! is_string($valor) || $valor === '') {
            return $valor;
        }

        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
    }
}