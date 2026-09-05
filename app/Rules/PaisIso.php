<?php

namespace App\Rules;

use App\Support\Geografia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O país tem de ser um código ISO 3166-1 alfa-2.
 *
 * Não é preciosismo: este valor viaja para a AGT como `customerCountry`, que
 * a DS.120 exige em duas letras. Um «Portugal» escrito à mão chegava lá com
 * oito caracteres, e o documento era recusado.
 */
class PaisIso implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Geografia::ehPaisValido(is_string($value) ? $value : null)) {
            return;
        }

        $sugestao = Geografia::normalizarPais(is_string($value) ? $value : null);

        $fail($sugestao
            ? __('Escolha o país da lista — «:valor» corresponde a :sugestao.', [
                'valor' => (string) $value, 'sugestao' => Geografia::nomeDoPais($sugestao) . " ({$sugestao})",
            ])
            : __('Escolha um país da lista. A AGT só aceita o código de duas letras.'));
    }
}
