<?php

namespace App\Support;

/**
 * As regras do PIN de turno, num sítio só.
 *
 * A lista dos PIN óbvios estava escrita três vezes — na Gestão de
 * Utilizadores, no auto-serviço e no comando `pwa:definir-pin` — e já não
 * eram iguais: o comando recusava o 4321 e os ecrãs aceitavam-no. É a
 * avaria do E39 noutro fato: duas implementações da mesma regra acabam
 * sempre por divergir. Agora há uma, e o aparelho recebe-a na sincronização
 * para recusar o mesmo que o servidor recusa quando o PIN se repõe sem rede.
 */
final class PinDeTurno
{
    public const MIN = 4;
    public const MAX = 6;

    /** Os clássicos que qualquer pessoa tenta primeiro. */
    private const CLASSICOS = ['1234', '4321', '123456', '654321', '2580', '0852', '1004', '2000', '1010', '2020'];

    /**
     * Todos os PIN que não protegem nada: os clássicos, os de dígitos todos
     * iguais e as escadas (crescentes e decrescentes) de 4 a 6 dígitos.
     *
     * Gera-se em vez de se escrever à mão — uma lista escrita esquece-se de
     * um, e é esse que fica.
     *
     * @return list<string>
     */
    public static function obvios(): array
    {
        $lista = self::CLASSICOS;

        for ($n = self::MIN; $n <= self::MAX; $n++) {
            for ($d = 0; $d <= 9; $d++) {
                $lista[] = str_repeat((string) $d, $n);
            }

            for ($inicio = 0; $inicio + $n <= 10; $inicio++) {
                $escada = '';
                for ($i = 0; $i < $n; $i++) {
                    $escada .= (string) ($inicio + $i);
                }
                $lista[] = $escada;
                $lista[] = strrev($escada);
            }
        }

        return array_values(array_unique($lista));
    }

    public static function ehObvio(string $pin): bool
    {
        return in_array($pin, self::obvios(), true);
    }

    /** A razão de o PIN não servir, ou null se serve. */
    public static function recusa(string $pin): ?string
    {
        if (!preg_match('/^\d{' . self::MIN . ',' . self::MAX . '}$/', $pin)) {
            return 'O PIN tem de ter ' . self::MIN . ' a ' . self::MAX . ' dígitos.';
        }

        if (self::ehObvio($pin)) {
            return 'Escolha um PIN menos óbvio.';
        }

        return null;
    }

    /** O que o aparelho precisa de saber para recusar o mesmo que o servidor. */
    public static function regrasParaOAparelho(): array
    {
        return [
            'min'    => self::MIN,
            'max'    => self::MAX,
            'obvios' => self::obvios(),
        ];
    }

    /**
     * É um verificador bcrypt como o bcryptjs do aparelho ou o Laravel
     * produzem? 60 caracteres: prefixo, custo, sal e hash.
     */
    public static function ehVerificadorBcrypt(?string $hash): bool
    {
        return is_string($hash)
            && preg_match('/^\$2[abxy]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $hash) === 1;
    }

    /**
     * O bcryptjs escreve `$2a$`; o Laravel escreve `$2y$`. O algoritmo é o
     * mesmo — guarda-se sempre como o Laravel guarda, para a coluna ter uma
     * cara só.
     */
    public static function normalizarVerificador(string $hash): string
    {
        // Sem preg_replace: no texto de substituição um `$2` é a referência ao
        // grupo 2, e o `$2y$` saía como `y$` — guardou-se um hash sem prefixo
        // que nada conseguia verificar. Apanhado no ensaio e no Android.
        if (preg_match('/^\$2[abxy]\$/', $hash) === 1) {
            return '$2y$' . substr($hash, 4);
        }

        return $hash;
    }
}
