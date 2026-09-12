<?php

namespace App\Support;

/**
 * EM QUANTAS PARTES UM SMS VAI — que é o que se paga.
 *
 * Um único acento leva a mensagem para UCS-2, e aí cada parte encolhe de 160
 * para 70 caracteres. Contar caracteres em vez de partes escondia isso: uma
 * mensagem de 160 letras é uma parte, e a mesma com «ã» são três.
 *
 * O ecrã React faz a mesma conta para a mostrar enquanto se escreve; a que
 * vale para o envio é esta.
 */
final class PartesDeSms
{
    private const GSM_UMA = 160;
    private const GSM_VARIAS = 153;
    private const UCS2_UMA = 70;
    private const UCS2_VARIAS = 67;

    public static function contar(string $texto): int
    {
        $texto = trim($texto);
        $tamanho = mb_strlen($texto);

        if ($tamanho === 0) {
            return 0;
        }

        $unicode = $tamanho !== strlen($texto);
        $umaSo = $unicode ? self::UCS2_UMA : self::GSM_UMA;
        $varias = $unicode ? self::UCS2_VARIAS : self::GSM_VARIAS;

        return $tamanho <= $umaSo ? 1 : (int) ceil($tamanho / $varias);
    }
}
