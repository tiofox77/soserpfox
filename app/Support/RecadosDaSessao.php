<?php

namespace App\Support;

/**
 * OS RECADOS QUE UM REDIRECT DEIXA NA SESSÃO — `->with('error', …)`.
 *
 * Os ecrãs eram Blade e cada um desenhava os seus. Passaram a React, e os
 * recados de quem redirecciona para lá ficaram a falar sozinhos: a ligação ao
 * KiandaStay («não foi possível concluir a ligação»), o QR da carta sem
 * endereço, a conta sem empresa activa — o redirect acontecia e a pessoa não
 * sabia porquê.
 *
 * O layout entrega-os à peça `casca/sistema`, que os mostra como avisos de
 * canto. Cada recado sai uma vez: é um flash, a sessão já o esquece sozinha.
 */
final class RecadosDaSessao
{
    /** A chave da sessão → o tipo de aviso. */
    private const TIPOS = [
        'success' => 'ok',
        'status' => 'ok',
        'message' => 'ok',
        'error' => 'erro',
        'warning' => 'aviso',
        'info' => 'info',
    ];

    /**
     * @param  array<int, string>  $sem  as chaves que o ecrã desta página já mostra
     * @return array<int, array{tipo: string, texto: string}>
     */
    public static function lista(array $sem = []): array
    {
        $recados = [];

        foreach (self::TIPOS as $chave => $tipo) {
            if (in_array($chave, $sem, true)) {
                continue;
            }

            $valor = session($chave);

            if (is_string($valor) && trim($valor) !== '') {
                $recados[] = ['tipo' => $tipo, 'texto' => $valor];
            }
        }

        return $recados;
    }
}
