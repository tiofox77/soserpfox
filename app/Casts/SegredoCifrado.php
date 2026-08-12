<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Um segredo que fica cifrado na base de dados.
 *
 * Serve senhas de SMTP e tokens de operadoras — coisas que, lidas por quem
 * chegue à base, dão acesso a contas de terceiros: mandar email em nome da
 * empresa, gastar o saldo de SMS dela.
 *
 * PORQUE NÃO É O CAST `encrypted` DO LARAVEL
 * ------------------------------------------
 * Porque já há valores gravados em texto simples. O cast do Laravel tenta
 * decifrar sempre e lança `DecryptException` no primeiro que encontrar —
 * rebentava o ecrã de definições de todas as empresas que já tinham
 * configuração, e obrigava a uma migração a correr ANTES do código, com uma
 * janela pelo meio em que o envio ficava partido.
 *
 * Este lê os dois: se o valor está cifrado, decifra; se não, devolve-o como
 * está. Escreve sempre cifrado. Assim cada gravação converte a sua linha, sem
 * migração e sem janela nenhuma.
 */
class SegredoCifrado implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Ainda em texto simples, de antes deste cast existir. Devolve-se
            // como está — fica cifrado na próxima gravação.
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
