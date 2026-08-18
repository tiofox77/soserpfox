<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Um email para onde se possa mesmo escrever.
 *
 * Os domínios abaixo estão RESERVADOS por norma (RFC 2606 e RFC 6761) e nunca
 * chegam a lado nenhum: `.local`, `.test`, `.invalid`, `.example`. Aparecem em
 * registos de teste e em ensaios que escapam — e depois ninguém consegue
 * avisar a empresa de nada, nem recuperar a palavra-passe.
 *
 * As caixas descartáveis também: quem se regista com uma perde o acesso à
 * conta dentro de dias e vem pedir ajuda sem forma de provar quem é.
 */
class EmailQueExiste implements ValidationRule
{
    /** Domínios que por norma não existem. */
    private const RESERVADOS = ['local', 'test', 'invalid', 'example', 'localhost'];

    /** Caixas de correio que se apagam sozinhas. */
    private const DESCARTAVEIS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'yopmail.com', 'trashmail.com', 'sharklasers.com', 'getnada.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = mb_strtolower(trim((string) $value));
        $dominio = substr(strrchr($email, '@') ?: '', 1);

        if ($dominio === '') {
            return;   // a regra `email` do Laravel trata da forma
        }

        $tld = substr(strrchr($dominio, '.') ?: '', 1);

        if (in_array($tld, self::RESERVADOS, true) || in_array($dominio, self::RESERVADOS, true)) {
            $fail('Use um email que receba mensagens: :dominio não existe fora deste computador.')
                ->translate(['dominio' => $dominio]);

            return;
        }

        if (in_array($dominio, self::DESCARTAVEIS, true)) {
            $fail('Use um email permanente. Caixas temporárias fazem-lhe perder o acesso à conta.');
        }
    }
}
