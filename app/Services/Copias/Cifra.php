<?php

namespace App\Services\Copias;

use RuntimeException;

/**
 * CIFRAR UMA CÓPIA COM UMA FRASE-PASSE — antes de ela sair para a nuvem.
 *
 * Uma cópia da base leva tudo: NIFs, moradas, salários, facturas. Guardá-la
 * num Google Drive ou num FTP alheio sem cifra é entregar tudo a quem lá chegar
 * (e o RGPD chama-lhe violação de dados). Com a frase ligada, o que sai do
 * servidor é ilegível sem ela.
 *
 * libsodium, que o PHP já traz: a chave sai da frase por Argon2id (lento de
 * propósito, contra quem tente adivinhar) e o ficheiro cifra-se por pedaços de
 * 1 MB com XChaCha20-Poly1305 (secretstream) — nunca o ficheiro inteiro em
 * memória, e um pedaço trocado ou cortado é detectado.
 *
 * O FORMATO, para se poder decifrar noutro servidor com `copias:decifrar`:
 *   "SOSCOPIA1" | sal (16) | cabeçalho do secretstream (24) |
 *   repetido: tamanho do pedaço cifrado (4, big-endian) | pedaço cifrado
 */
class Cifra
{
    public const MAGIA = 'SOSCOPIA1';

    private const PEDACO = 1048576;

    public static function estaCifrado(string $caminho): bool
    {
        $f = @fopen($caminho, 'rb');
        if (! $f) {
            return false;
        }
        $inicio = fread($f, strlen(self::MAGIA));
        fclose($f);

        return $inicio === self::MAGIA;
    }

    public static function cifrar(string $origem, string $destino, string $frase): void
    {
        self::validarFrase($frase);

        $sal = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $chave = self::chave($frase, $sal);
        [$estado, $cabecalho] = sodium_crypto_secretstream_xchacha20poly1305_init_push($chave);

        $in = fopen($origem, 'rb');
        $out = fopen($destino, 'wb');
        if (! $in || ! $out) {
            throw new RuntimeException('Não foi possível abrir os ficheiros para cifrar.');
        }

        fwrite($out, self::MAGIA . $sal . $cabecalho);

        $tamanho = filesize($origem);
        $lido = 0;

        do {
            $pedaco = fread($in, self::PEDACO);
            $pedaco = $pedaco === false ? '' : $pedaco;
            $lido += strlen($pedaco);
            $ultimo = $lido >= $tamanho;
            $etiqueta = $ultimo ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $cifrado = sodium_crypto_secretstream_xchacha20poly1305_push($estado, $pedaco, '', $etiqueta);
            fwrite($out, pack('N', strlen($cifrado)) . $cifrado);
        } while (! $ultimo);

        fclose($in);
        fclose($out);
        sodium_memzero($chave);
    }

    public static function decifrar(string $origem, string $destino, string $frase): void
    {
        $in = fopen($origem, 'rb');
        if (! $in || fread($in, strlen(self::MAGIA)) !== self::MAGIA) {
            throw new RuntimeException('O ficheiro não é uma cópia cifrada do SOSERP.');
        }

        $sal = fread($in, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $cabecalho = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $chave = self::chave($frase, $sal);
        $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull($cabecalho, $chave);
        sodium_memzero($chave);

        $out = fopen($destino, 'wb');
        $acabou = false;

        while (! feof($in)) {
            $tamanho = fread($in, 4);
            if ($tamanho === '' || $tamanho === false) {
                break;
            }
            if (strlen($tamanho) !== 4) {
                throw new RuntimeException('A cópia cifrada está cortada.');
            }
            $cifrado = fread($in, unpack('N', $tamanho)[1]);
            $r = sodium_crypto_secretstream_xchacha20poly1305_pull($estado, $cifrado);
            if ($r === false) {
                fclose($out);
                @unlink($destino);
                throw new RuntimeException('Frase-passe errada, ou o ficheiro foi alterado.');
            }
            [$claro, $etiqueta] = $r;
            fwrite($out, $claro);
            if ($etiqueta === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                $acabou = true;
                break;
            }
        }

        fclose($in);
        fclose($out);

        if (! $acabou) {
            @unlink($destino);
            throw new RuntimeException('A cópia cifrada está incompleta.');
        }
    }

    public static function validarFrase(string $frase): void
    {
        if (mb_strlen($frase) < 12) {
            throw new RuntimeException('A frase-passe tem de ter pelo menos 12 caracteres.');
        }
    }

    private static function chave(string $frase, string $sal): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $frase,
            $sal,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }
}
