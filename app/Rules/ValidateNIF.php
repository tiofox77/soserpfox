<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidateNIF implements Rule
{
    public function __construct(private ?string $entityType = null)
    {
    }

    /**
     * Validação de NIF Angola
     * 
     * Regras:
     * - 9 ou 10 dígitos
     * - Pessoa Jurídica: começa com 5
     * - Pessoa Física: NIF numérico ou B.I. (9 dígitos + 2 letras + 3 dígitos)
     */
    public function passes($attribute, $value)
    {
        $document = self::normalize((string) $value);

        // Em Angola, para uma pessoa singular o número fiscal pode ser o
        // número do Bilhete de Identidade. Não se podem apagar as letras antes
        // desta verificação: 025824504LA054 é um documento válido e, ao tirar
        // "LA", tornava-se artificialmente um número de 12 dígitos.
        if ($this->entityType === 'pessoa_fisica'
            && preg_match('/^\d{9}[A-Z]{2}\d{3}$/', $document) === 1) {
            return true;
        }

        $nif = preg_replace('/[\s.\-\/]+/', '', $document);
        
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
        if ($this->entityType === 'pessoa_fisica') {
            return 'O :attribute deve ser um NIF angolano válido (9-10 dígitos) ou um B.I. no formato 000000000LA000.';
        }

        return 'O :attribute não é um NIF válido de Angola. Deve ter 9-10 dígitos e começar com 2 (Pessoa Física), 3 (Estrangeiro) ou 5 (Pessoa Jurídica).';
    }

    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s.\-\/]+/', '', trim($value)));
    }
}
