<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O NIF de quem se regista: de EMPRESA, ou de EMPRESÁRIO EM NOME INDIVIDUAL.
 *
 * Em Angola o NIF de pessoa colectiva é atribuído pela AGT e tem dez dígitos
 * começados por 5. O de pessoa singular é o próprio número do BI — nove
 * dígitos, duas letras da província e três dígitos, como 004512345LA041. A AGT
 * usa-o como NIF desde 2019, com ou sem actividade comercial.
 *
 * O NÚMERO DO BI PASSA (26/09/2026, decisão do dono da plataforma): uma
 * empresa com NIF singular — o empresário em nome individual — usa o sistema
 * como as outras. O que se trava é o número MAL ESCRITO: com letras mas fora
 * do feitio do BI (um dígito a mais, por exemplo), ou só algarismos que não
 * são de empresa. O NIF vai em cada documento fiscal comunicado à AGT, e um
 * número errado só se descobre quando as facturas começam a ser recusadas.
 *
 * Desde 22/09/2026 aceita também DEZ dígitos começados por 0: há alvarás
 * comerciais com NIF assim (0000083092, de um empresário em nome individual),
 * e recusá-lo deixava um contribuinte real sem se poder registar. Nove
 * começados por 0 continuam fora — são os algarismos do BI sem as letras.
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

        // O NÚMERO DO BI É O NIF DE PESSOA SINGULAR (26/09/2026): o de um
        // empresário em nome individual. A AGT usa-o como NIF desde 2019, e
        // uma empresa assim usa o sistema como as outras. Com letras mas fora
        // do feitio do BI, é um número mal escrito — e diz-se qual é o feitio.
        if (preg_match('/[A-Za-z]/', $limpo)) {
            if (!\App\Support\NifAngolano::formatoDeBI($limpo)) {
                $fail('O NIF de pessoa singular é o número do BI: nove dígitos, duas letras e três dígitos (por exemplo 004512345LA041).');
            }

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

        // Começado por 5, ou dez dígitos começados por 0 — a regra única está
        // em NifAngolano::formatoDeEmpresa, que diz porquê.
        if (!\App\Support\NifAngolano::formatoDeEmpresa($limpo)) {
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
