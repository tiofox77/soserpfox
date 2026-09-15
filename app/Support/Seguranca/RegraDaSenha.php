<?php

namespace App\Support\Seguranca;

use Illuminate\Validation\Rules\Password;

/**
 * UMA REGRA DE SENHA para toda a gente que escolhe uma.
 *
 * Havia duas: 6 caracteres no registo, no convite e na criação de
 * utilizadores; 8 na recuperação e na «Minha conta». «123456» passava na porta
 * de entrada e era recusada na de saída. Passa a ser uma só — 8 caracteres com
 * letras e números — registada como `Password::defaults()` no arranque, para
 * que qualquer ecrã novo que use `Password::defaults()` a siga sem saber dela.
 *
 * O texto de ajuda dos formulários (`dica()`) sai daqui para não voltar a
 * dizer «Mínimo 6» num sítio e «Mínimo 8» noutro.
 */
class RegraDaSenha
{
    public const MINIMO = 8;

    public static function regra(): Password
    {
        return Password::min(self::MINIMO)->letters()->numbers();
    }

    public static function dica(): string
    {
        return __('Mínimo 8 caracteres, com letras e números.');
    }
}
