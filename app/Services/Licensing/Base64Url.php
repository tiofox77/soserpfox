<?php

namespace App\Services\Licensing;

/**
 * Base64 "URL-safe" e sem padding — o alfabeto que se pode meter num token,
 * num URL ou num ficheiro sem apanhar `+`, `/` ou `=` pelo caminho.
 */
class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $texto): string
    {
        $b64 = strtr($texto, '-_', '+/');
        // Repõe o padding que o encode retirou.
        $resto = strlen($b64) % 4;
        if ($resto) {
            $b64 .= str_repeat('=', 4 - $resto);
        }

        return (string) base64_decode($b64, true);
    }
}
