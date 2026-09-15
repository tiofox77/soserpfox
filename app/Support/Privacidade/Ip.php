<?php

namespace App\Support\Privacidade;

/**
 * O IP QUE SE GUARDA É O DA REDE, NÃO O DA PESSOA.
 *
 * Um IP inteiro é um dado pessoal (TJUE, Breyer, 2016): identifica a ligação
 * de alguém. Para as estatísticas basta saber de que rede veio — o país e a
 * cidade continuam a resolver-se — e é isso que se guarda: o último octeto de
 * um IPv4 a zero (x.x.x.0), e de um IPv6 só o prefixo /48.
 *
 * NÃO se usa na trilha de auditoria nem nas entradas falhadas: aí o IP inteiro
 * é o que permite bloquear um ataque, e a base legal é a segurança.
 */
class Ip
{
    public static function anonimizar(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binario = inet_pton($ip);

            // 48 bits = 6 bytes; o resto a zero.
            return inet_ntop(substr($binario, 0, 6) . str_repeat("\0", 10));
        }

        return null;
    }
}
