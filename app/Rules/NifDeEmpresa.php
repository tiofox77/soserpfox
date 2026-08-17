<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O NIF tem de ser de EMPRESA, e não o do bilhete de identidade.
 *
 * Em Angola o NIF de pessoa colectiva é atribuído pela AGT e tem dez dígitos
 * começados por 5. O de pessoa singular é o próprio número do BI — nove
 * dígitos, duas letras da província e três dígitos, como 004512345LA041 — e
 * chega a começar por 0 ou por 2.
 *
 * Porque é que isto tem de ser travado à entrada: o NIF da empresa vai em cada
 * documento fiscal comunicado à AGT, e é por ele que a empresa é identificada.
 * Registar-se com o NIF do BI passa despercebido durante semanas e só aparece
 * quando as facturas começam a ser recusadas — altura em que já há documentos
 * emitidos com o número errado e a correcção deixou de ser só mudar um campo.
 *
 * A regra ValidateNIF, que já existia, aceita 2, 3 e 5, e está certa onde é
 * usada: nos CLIENTES, que tanto podem ser empresas como pessoas. Esta é para
 * quem se regista como empresa.
 */
class NifDeEmpresa implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $limpo = $this->limpar($value);

        if ($limpo === '') {
            $fail('Indique o NIF da empresa.');

            return;
        }

        // As letras denunciam o BI. Vale a pena dizê-lo por palavras: quem
        // escreve o número do BI não está a errar por distracção, está a achar
        // que é aquele o número — e uma mensagem genérica não o demove.
        if (preg_match('/[A-Za-z]/', $limpo)) {
            $fail('Esse é o número do bilhete de identidade. O NIF da empresa tem nove ou dez dígitos e começa por 5.');

            return;
        }

        // Nove ou dez, e nao so dez: das empresas ja registadas, oito tem dez
        // digitos e uma tem nove — todas comecadas por 5. Exigir dez rejeitava
        // um cliente real, e o que distingue empresa de pessoa e o 5, nao o
        // comprimento.
        if (!preg_match('/^\d{9,10}$/', $limpo)) {
            $fail('O NIF da empresa tem nove ou dez dígitos.');

            return;
        }

        if (!str_starts_with($limpo, '5')) {
            // ->translate() e não um segundo argumento do $fail(): o $fail
            // recebe só a mensagem, e as substituições fazem-se no que ele
            // devolve. Passadas como argumento, o ":inicio" chegava ao ecrã tal
            // e qual, escrito por extenso.
            $fail('O NIF da empresa começa por 5. Um número começado por :inicio é de pessoa singular.')
                ->translate(['inicio' => $limpo[0]]);

            return;
        }
    }

    /**
     * Tira espaços, pontos e traços — e mais nada.
     *
     * As letras ficam de propósito: são elas que permitem dizer a quem escreveu
     * o BI que foi isso que escreveu. Limpá-las aqui transformava 004512345LA041
     * em doze dígitos e a mensagem passava a ser sobre o comprimento, que não
     * explica nada a ninguém.
     */
    private function limpar(mixed $valor): string
    {
        return preg_replace('/[\s.\-\/]/', '', (string) $valor) ?? '';
    }
}
