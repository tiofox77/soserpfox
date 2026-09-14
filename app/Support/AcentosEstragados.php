<?php

namespace App\Support;

/**
 * «├üCIDO F├ôLICO» → «ÁCIDO FÓLICO».
 *
 * O QUE ACONTECEU. Um texto em UTF-8 foi lido como se estivesse na página de
 * código do DOS (CP437/CP850) — ou, noutros casos, em Latin-1/CP1252 — e
 * gravado outra vez em UTF-8. Cada letra acentuada ocupa dois bytes em UTF-8
 * (Á = C3 81) e cada byte virou um carácter: C3 → «├», 81 → «ü». Foi o que
 * trouxe a importação da farmácia (base antiga SMA) para centenas de artigos.
 *
 * COMO SE DESFAZ. Voltando a pôr cada carácter no byte de onde veio e lendo
 * os bytes como UTF-8. Mas NÃO se converte o texto inteiro: um nome pode ter
 * letras certas ao lado das estragadas, e essas estragavam-se. Repara-se só
 * uma SEQUÊNCIA com a forma de uma letra UTF-8 estragada:
 *
 *  • começa num carácter que é o byte de arranque (C2–C5, E2) — em CP437 e
 *    CP850 são traços de desenhar caixas («├ ┬ ─ ┼»), que nunca aparecem num
 *    nome verdadeiro; em CP1252 são «Â Ã â», e aí só se aceita o que dá um
 *    carácter acentuado ou pontuação;
 *  • segue-se o número certo de caracteres que são bytes de continuação;
 *  • os bytes lidos como UTF-8 dão uma letra latina, um símbolo (º ª °) ou
 *    pontuação (– “ ” …) — nunca outra coisa.
 *
 * O que não tem essa forma fica exactamente como estava. Um texto pode ter
 * sido estragado duas vezes; repara-se até deixar de mudar (no máximo três).
 */
final class AcentosEstragados
{
    /** Os caracteres dos bytes 0x80–0xFF em cada página de código. */
    private const CP437 = 'ÇüéâäàåçêëèïîìÄÅÉæÆôöòûùÿÖÜ¢£¥₧ƒáíóúñÑªº¿⌐¬½¼¡«»░▒▓│┤╡╢╖╕╣║╗╝╜╛┐└┴┬├─┼╞╟╚╔╩╦╠═╬╧╨╤╥╙╘╒╓╫╪┘┌█▄▌▐▀αßΓπΣσµτΦΘΩδ∞φε∩≡±≥≤⌠⌡÷≈°∙·√ⁿ²■' . "\u{00A0}";

    private const CP850 = 'ÇüéâäàåçêëèïîìÄÅÉæÆôöòûùÿÖÜø£Ø×ƒáíóúñÑªº¿®¬½¼¡«»░▒▓│┤ÁÂÀ©╣║╗╝¢¥┐└┴┬├─┼ãÃ╚╔╩╦╠═╬¤ðÐÊËÈıÍÎÏ┘┌█▄¦Ì▀ÓßÔÒõÕµþÞÚÛÙýÝ¯´' . "\u{00AD}" . '±‗¾¶§÷¸°¨·¹³²■' . "\u{00A0}";

    /** Os bytes de arranque aceites em cada página (ver o cabeçalho). */
    private const ARRANQUES = [
        'CP437' => [0xC2, 0xC3, 0xC4, 0xC5, 0xE2],
        'CP850' => [0xC2, 0xC3, 0xC4, 0xC5, 0xE2],
        'CP1252' => [0xC2, 0xC3, 0xE2],
    ];

    /** @var array<string, array<string, int>> carácter → byte, por página */
    private static array $mapas = [];

    public static function reparar(?string $texto): ?string
    {
        if ($texto === null || $texto === '' || ! self::talvezEstragado($texto)) {
            return $texto;
        }

        $actual = $texto;

        for ($volta = 0; $volta < 3; $volta++) {
            $seguinte = self::umaVolta($actual);

            if ($seguinte === $actual) {
                break;
            }

            $actual = $seguinte;
        }

        return $actual;
    }

    /**
     * Ficou alguma coisa que não se soube reparar? Traços de caixa ou o
     * carácter de substituição num nome são sinal de que alguém tem de olhar.
     */
    public static function precisaDeOlhos(?string $texto): bool
    {
        return $texto !== null && (bool) preg_match('/[\x{2500}-\x{259F}\x{FFFD}]/u', $texto);
    }

    /** Um filtro barato antes do trabalho todo. */
    private static function talvezEstragado(string $texto): bool
    {
        return (bool) preg_match('/[├┬─┼ΓÔÂÃâ]/u', $texto);
    }

    private static function umaVolta(string $texto): string
    {
        $chars = mb_str_split($texto);
        $total = count($chars);
        $saida = '';

        for ($i = 0; $i < $total; $i++) {
            $reparado = null;

            foreach (['CP437', 'CP850', 'CP1252'] as $pagina) {
                $reparado = self::sequencia($chars, $i, $pagina);

                if ($reparado !== null) {
                    break;
                }
            }

            if ($reparado === null) {
                $saida .= $chars[$i];
                continue;
            }

            [$letra, $comprimento] = $reparado;
            $saida .= $letra;
            $i += $comprimento - 1;
        }

        return $saida;
    }

    /** @return array{0: string, 1: int}|null a letra e quantos caracteres gastou */
    private static function sequencia(array $chars, int $i, string $pagina): ?array
    {
        $mapa = self::mapa($pagina);
        $arranque = $mapa[$chars[$i]] ?? null;

        if ($arranque === null || ! in_array($arranque, self::ARRANQUES[$pagina], true)) {
            return null;
        }

        $comprimento = $arranque >= 0xE0 ? 3 : 2;

        if ($i + $comprimento > count($chars)) {
            return null;
        }

        $bytes = chr($arranque);

        for ($k = 1; $k < $comprimento; $k++) {
            $b = $mapa[$chars[$i + $k]] ?? null;

            if ($b === null || $b < 0x80 || $b > 0xBF) {
                return null;
            }

            $bytes .= chr($b);
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            return null;
        }

        $codigo = mb_ord($bytes, 'UTF-8');

        // Em CP1252 aceita-se também um traço de caixa: é o passo do meio de um
        // texto estragado DUAS vezes («â”œ» → «├» → na volta seguinte, a letra).
        $passoDoMeio = $pagina === 'CP1252' && $codigo >= 0x2500 && $codigo <= 0x257F;

        return self::plausivel($codigo) || $passoDoMeio ? [$bytes, $comprimento] : null;
    }

    /** Uma letra latina, um símbolo de Latin-1 ou pontuação — o que um nome tem. */
    private static function plausivel(int $codigo): bool
    {
        return ($codigo >= 0x00A0 && $codigo <= 0x017F)
            || ($codigo >= 0x2010 && $codigo <= 0x2026)
            || $codigo === 0x2030
            || $codigo === 0x20AC
            || $codigo === 0x2122;
    }

    /** @return array<string, int> */
    private static function mapa(string $pagina): array
    {
        if (isset(self::$mapas[$pagina])) {
            return self::$mapas[$pagina];
        }

        $mapa = [];

        if ($pagina === 'CP1252') {
            // 0x80–0x9F têm os caracteres próprios do Windows; 0xA0–0xFF são os de Latin-1.
            $proprios = [0x80 => '€', 0x82 => '‚', 0x83 => 'ƒ', 0x84 => '„', 0x85 => '…', 0x86 => '†', 0x87 => '‡', 0x88 => 'ˆ', 0x89 => '‰', 0x8A => 'Š', 0x8B => '‹', 0x8C => 'Œ', 0x8E => 'Ž',
                0x91 => '‘', 0x92 => '’', 0x93 => '“', 0x94 => '”', 0x95 => '•', 0x96 => '–', 0x97 => '—', 0x98 => '˜', 0x99 => '™', 0x9A => 'š', 0x9B => '›', 0x9C => 'œ', 0x9E => 'ž', 0x9F => 'Ÿ'];

            foreach ($proprios as $b => $c) {
                $mapa[$c] = $b;
            }

            // Os cinco bytes sem carácter no CP1252 ficam como os controlos C1 (é o que o MySQL faz).
            foreach ([0x81, 0x8D, 0x8F, 0x90, 0x9D] as $b) {
                $mapa[mb_chr($b, 'UTF-8')] = $b;
            }

            for ($b = 0xA0; $b <= 0xFF; $b++) {
                $mapa[mb_chr($b, 'UTF-8')] = $b;
            }
        } else {
            foreach (mb_str_split(constant(self::class . '::' . $pagina)) as $n => $c) {
                $mapa[$c] = 0x80 + $n;
            }
        }

        return self::$mapas[$pagina] = $mapa;
    }
}
