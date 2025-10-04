<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidateNIF implements Rule
{
    /**
     * Validação de NIF Angola
     * 
     * Regras:
     * - 9 ou 10 dígitos
     * - Pessoa Jurídica: começa com 5
     * - Pessoa Física: começa com 2
     */
    public function passes($attribute, $value)
    {
        // Remove caracteres não numéricos
        $nif = preg_replace('/[^0-9]/', '', $value);
        
        // Verifica comprimento
        if (strlen($nif) < 9 || strlen($nif) > 10) {
            return false;
        }
        
        // Verifica se são apenas números
        if (!ctype_digit($nif)) {
            return false;
        }
        
        // Verifica primeiro dígito
        $firstDigit = substr($nif, 0, 1);
        
        // 2 = Pessoa Física, 5 = Pessoa Jurídica, 3 = Estrangeiros
        return in_array($firstDigit, ['2', '3', '5']);
    }

    public function message()
    {
        return 'O :attribute não é um NIF válido de Angola. Deve ter 9-10 dígitos e começar com 2 (Pessoa Física), 3 (Estrangeiro) ou 5 (Pessoa Jurídica).';
    }
}
