<?php

namespace App\Support;

/**
 * O TELEFONE COM QUE O CLIENTE ENTRA NO PORTAL (15/09/2026).
 *
 * Escreve-se de mil maneiras — «923 456 789», «+244 923456789», «00244-923…» —
 * e tem de bater sempre. Guarda-se e compara-se só com os algarismos, sem o
 * indicativo de Angola; números estrangeiros ficam com todos os algarismos.
 */
class TelefoneDoPortal
{
    public static function normalizar(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefone);

        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }
        if (strlen($digitos) === 12 && str_starts_with($digitos, '244')) {
            $digitos = substr($digitos, 3);
        }

        // Menos de 9 algarismos não identifica ninguém.
        return strlen($digitos) >= 9 ? substr($digitos, 0, 20) : null;
    }

    /** O que se escreveu na entrada parece um telefone? (só algarismos, espaços, +, -, parênteses) */
    public static function pareceTelefone(string $texto): bool
    {
        return (bool) preg_match('/^[\d\s+\-().]{9,}$/', trim($texto)) && self::normalizar($texto) !== null;
    }

    /** O nome de utilizador como se guarda e se compara: minúsculas, sem espaços à volta. */
    public static function utilizador(?string $nome): ?string
    {
        $nome = mb_strtolower(trim((string) $nome));

        return $nome === '' ? null : $nome;
    }
}
