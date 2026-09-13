<?php

namespace App\Support;

/**
 * OS ACENTOS QUE UMA IMPORTAÇÃO ESTRAGOU.
 *
 * Listas de artigos vindas de um sistema antigo chegaram com os acentos lidos
 * na página de código da consola do Windows (CP437) e gravados assim:
 * «ÁLCOOL» ficou «├üLCOOL», «SOLUçÃO» ficou «SOLU├ºÃO». Cada letra acentuada
 * são dois bytes UTF-8, e cada byte virou um símbolo da tabela CP437 — o
 * primeiro é quase sempre «├» (0xC3) ou «┬» (0xC2).
 *
 * O caminho de volta é o mesmo ao contrário: o símbolo diz o byte, os dois
 * bytes dizem a letra. Só se trocam os pares que dão uma letra válida; o resto
 * do texto fica como está (há nomes com metade estragada e metade certa).
 */
final class TextoEstragado
{
    /** A metade de cima da CP437 que interessa: os bytes 0x80 a 0xBF. */
    private const CP437 = [
        'Ç', 'ü', 'é', 'â', 'ä', 'à', 'å', 'ç', 'ê', 'ë', 'è', 'ï', 'î', 'ì', 'Ä', 'Å',
        'É', 'æ', 'Æ', 'ô', 'ö', 'ò', 'û', 'ù', 'ÿ', 'Ö', 'Ü', '¢', '£', '¥', '₧', 'ƒ',
        'á', 'í', 'ó', 'ú', 'ñ', 'Ñ', 'ª', 'º', '¿', '⌐', '¬', '½', '¼', '¡', '«', '»',
        '░', '▒', '▓', '│', '┤', '╡', '╢', '╖', '╕', '╣', '║', '╗', '╝', '╜', '╛', '┐',
    ];

    public static function estaEstragado(?string $texto): bool
    {
        return $texto !== null && self::reparar($texto) !== $texto;
    }

    public static function reparar(?string $texto): ?string
    {
        if ($texto === null || (! str_contains($texto, '├') && ! str_contains($texto, '┬'))) {
            return $texto;
        }

        static $posicao = null;
        $posicao ??= array_flip(self::CP437);

        return preg_replace_callback('/([├┬])(.)/u', function (array $m) use ($posicao): string {
            if (! isset($posicao[$m[2]])) {
                return $m[0];
            }

            $segundo = 0x80 + $posicao[$m[2]];

            // C2 80–9F são caracteres de controlo, não letras.
            if ($m[1] === '┬' && $segundo < 0xA0) {
                return $m[0];
            }

            return chr($m[1] === '├' ? 0xC3 : 0xC2) . chr($segundo);
        }, $texto) ?? $texto;
    }
}
