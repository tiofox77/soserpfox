<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Um nome tem de parecer um nome.
 *
 * Não é para adivinhar nomes verdadeiros — é para travar o lixo evidente:
 * "asdasd", "D 6a8441fb975b3", "teste123". Registos assim entram na base,
 * disparam avisos a quem administra, ocupam a fila de aprovação e ficam lá
 * para sempre porque ninguém sabe se são reais.
 *
 * A régua é deliberadamente baixa. Recusar um nome verdadeiro é pior do que
 * deixar passar um falso: quem se está a registar desiste e vai-se embora,
 * e não há como lhe explicar o que fez de errado.
 */
class NomeQueParecePessoa implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nome = trim((string) $value);

        if (mb_strlen($nome) < 3) {
            $fail('O nome está demasiado curto.');

            return;
        }

        // Sem uma única vogal não é uma palavra de nenhuma língua que se
        // escreva por aqui — é teclado esmagado ou um identificador.
        if (!preg_match('/[aeiouáàâãéêíóôõúAEIOUÁÀÂÃÉÊÍÓÔÕÚ]/u', $nome)) {
            $fail('Escreva o nome por extenso.');

            return;
        }

        foreach (preg_split('/\s+/u', $nome) as $palavra) {
            // Sequências longas de hexadecimal são identificadores gerados,
            // não palavras: "6a8441fb975b3", "e0WvJtk2kSAMndTz".
            if (mb_strlen($palavra) >= 8 && preg_match('/^[0-9a-f]+$/i', $palavra) && preg_match('/\d/', $palavra)) {
                $fail('O nome parece um código gerado. Escreva o nome real.');

                return;
            }

            // Três iguais seguidas: "aaa", "sssss".
            if (preg_match('/(.)\1{2,}/u', $palavra)) {
                $fail('Escreva o nome real.');

                return;
            }
        }

        // Só dígitos e espaços.
        if (!preg_match('/\p{L}{2,}/u', $nome)) {
            $fail('O nome tem de ter letras.');
        }
    }
}
