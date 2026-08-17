<?php

namespace App\Support;

/**
 * As formas equivalentes de um código de barras lido.
 *
 * O mesmo produto pode estar guardado de duas maneiras conforme o leitor e o
 * sistema de onde veio o catálogo. Uma caixa com código GS1-128 traz o
 * envelope à frente — o "01" é um identificador de aplicação, a etiqueta que
 * diz "o que vem a seguir é um código de produto" — e há leitores que o
 * devolvem e leitores que devolvem só o EAN-13 de dentro:
 *
 *   0108902292003269   ->  01 + GTIN-14 08902292003269  ->  EAN-13 8902292003269
 *
 * Quem passa o artigo na caixa não sabe nada disto e não tem de saber. Se o
 * catálogo tem uma forma e o leitor manda a outra, o POS diz "não existe" com
 * o produto à frente do operador. Daqui saem todas as formas possíveis, para
 * a procura tentar todas de uma vez.
 *
 * A ordem importa: o que foi lido vem primeiro, para uma correspondência
 * exacta ganhar sempre a uma equivalência.
 *
 * @see \Tests\Unit\CodigoDeBarrasTest
 */
class CodigoDeBarras
{
    /**
     * @return array<int,string> o lido e os equivalentes, sem repetições
     */
    public static function formas(string $lido): array
    {
        $lido = trim($lido);
        $digitos = preg_replace('/\D/', '', $lido) ?? '';

        $formas = [$lido];

        if ($digitos !== '' && $digitos !== $lido) {
            // Alguns leitores mandam os glifos do GS1-128 — ")01=" — que são
            // desenho da fonte, não são dados.
            $formas[] = $digitos;
        }

        $n = strlen($digitos);

        // Envelope GS1: AI(01) seguido de um GTIN-14. Atrás pode ainda vir
        // validade ou lote, que não fazem parte do código do produto.
        if ($n >= 16 && str_starts_with($digitos, '01')) {
            $gtin = substr($digitos, 2, 14);
            $formas[] = $gtin;

            if ($gtin[0] === '0') {
                $formas[] = substr($gtin, 1);
            }
        }

        // GTIN-14 é um EAN-13 com um zero de enchimento à frente.
        if ($n === 14 && $digitos[0] === '0') {
            $formas[] = substr($digitos, 1);
        }

        // Leu-se o EAN-13 simples, mas o catálogo pode tê-lo guardado com o
        // envelope — é o caso de quem importou de um sistema que guardava a
        // linha completa do leitor.
        if ($n === 13) {
            $formas[] = '0' . $digitos;
            $formas[] = '010' . $digitos;
        }

        return array_values(array_unique(array_filter($formas, fn ($f) => $f !== '')));
    }
}
