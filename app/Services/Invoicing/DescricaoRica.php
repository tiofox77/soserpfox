<?php

namespace App\Services\Invoicing;

/**
 * A DESCRIÇÃO FORMATADA DAS LINHAS DE PROFORMAS E ORÇAMENTOS.
 *
 * Nas propostas, a descrição de uma linha é onde se explica o trabalho: o
 * âmbito, as fases, o que está incluído. Escreve-se num editor (negrito,
 * listas, títulos, tabelas) e sai no PDF como foi escrita. Pedido de
 * 23/09/2026; a factura não precisa disto.
 *
 * TRÊS PORTAS, porque o mesmo texto vai para três sítios diferentes:
 *
 *  · `limpar()` antes de gravar. O HTML vem do browser e é impresso no PDF e
 *    mostrado no ecrã: fica só uma lista curta de etiquetas, sem atributos
 *    (salvo o alinhamento e o tamanho das células). Um <script>, um onclick ou
 *    um <img src=…> não sobrevivem.
 *  · `paraImprimir()` no papel. Uma descrição antiga, em texto simples, sai
 *    como sempre saiu (com as mudanças de linha).
 *  · `emTexto()` quando a proposta passa a FACTURA. A linha da factura vai à
 *    AGT (`productDescription`) e ao SAF-T, que não aceitam HTML.
 */
final class DescricaoRica
{
    /** Tamanho máximo do HTML gravado. A coluna é TEXT (64 KB). */
    public const MAXIMO = 20000;

    /** Etiqueta => atributos que ela pode levar. */
    private const PERMITIDAS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** Etiquetas que se deitam fora COM o que têm dentro. As outras desembrulham-se. */
    private const PERIGOSAS = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'select',
        'button', 'img', 'video', 'audio', 'link', 'meta', 'template', 'noscript', 'head', 'title', 'base',
    ];

    /**
     * Traz etiquetas de formatação? Texto antigo não. Uma etiqueta não tem
     * espaço depois do «<»: aceitá-lo fazia de «A < B» um <b>.
     */
    public static function eRica(?string $texto): bool
    {
        return $texto !== null
            && preg_match('~</?(p|br|strong|b|em|i|u|s|h[1-6]|ul|ol|li|blockquote|hr|table|div|span)(\s|/?>)~i', $texto) === 1;
    }

    /** O HTML que se pode gravar e imprimir. Texto simples passa como está. */
    public static function limpar(?string $html): ?string
    {
        if ($html === null || ! self::eRica($html)) {
            return $html;
        }

        $doc = new \DOMDocument();
        $antes = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($antes);

        $raiz = $doc->getElementById('raiz');

        if (! $raiz) {
            return trim(strip_tags($html));
        }

        self::limparFilhos($raiz);

        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $saida .= $doc->saveHTML($filho);
        }

        // Um editor vazio devolve «<p></p>»: isso é «sem descrição».
        return trim(strip_tags($saida)) === '' && ! str_contains($saida, '<hr') ? '' : trim($saida);
    }

    private static function limparFilhos(\DOMNode $no): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof \DOMComment || $filho instanceof \DOMProcessingInstruction) {
                $no->removeChild($filho);

                continue;
            }

            if (! $filho instanceof \DOMElement) {
                continue;
            }

            $nome = strtolower($filho->nodeName);

            if (in_array($nome, self::PERIGOSAS, true)) {
                $no->removeChild($filho);

                continue;
            }

            self::limparFilhos($filho);

            if (! array_key_exists($nome, self::PERMITIDAS)) {
                // Desembrulhar: fica o conteúdo, sai a etiqueta (um <div> ou um <span> do colar de outro programa).
                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }
                $no->removeChild($filho);

                continue;
            }

            $alinhamento = self::alinhamento($filho->getAttribute('style'));

            foreach (iterator_to_array($filho->attributes) as $atributo) {
                if (! in_array(strtolower($atributo->nodeName), self::PERMITIDAS[$nome], true)) {
                    $filho->removeAttribute($atributo->nodeName);
                }
            }

            foreach (self::PERMITIDAS[$nome] as $numerico) {
                if ($filho->hasAttribute($numerico)) {
                    $valor = max(1, min(20, (int) $filho->getAttribute($numerico)));
                    $valor === 1 ? $filho->removeAttribute($numerico) : $filho->setAttribute($numerico, (string) $valor);
                }
            }

            if ($alinhamento && in_array($nome, ['p', 'h2', 'h3', 'h4', 'li', 'th', 'td'], true)) {
                $filho->setAttribute('style', 'text-align: ' . $alinhamento);
            }
        }
    }

    private static function alinhamento(string $estilo): ?string
    {
        return preg_match('~text-align\s*:\s*(left|center|right|justify)\b~i', $estilo, $m) ? strtolower($m[1]) : null;
    }

    /**
     * O estilo da descrição formatada no papel, em medidas RELATIVAS: cada
     * modelo decide só o tamanho da letra (7 a 10 px, conforme o desenho).
     * Mais específico do que o `.items-table td` dos modelos, porque uma
     * tabela dentro da descrição apanhava o estilo das linhas do documento.
     */
    public static function estilo(): string
    {
        return '.descricao-rica{display:block;margin-top:2px;line-height:1.35;color:#333;white-space:normal}'
            . '.descricao-rica p{margin:0 0 .35em}'
            . '.descricao-rica h2{font-size:1.3em;margin:.5em 0 .25em;font-weight:bold}'
            . '.descricao-rica h3{font-size:1.15em;margin:.45em 0 .2em;font-weight:bold}'
            . '.descricao-rica h4{font-size:1.05em;margin:.4em 0 .2em;font-weight:bold}'
            . '.descricao-rica ul,.descricao-rica ol{margin:.2em 0 .4em 1.4em;padding:0}'
            . '.descricao-rica li{margin:0 0 .15em}.descricao-rica li p{margin:0}'
            . '.descricao-rica blockquote{margin:.3em 0;padding-left:.6em;border-left:2px solid #ccc;color:#555}'
            . '.descricao-rica hr{border:0;border-top:.5px solid #bbb;margin:.4em 0}'
            . 'table .descricao-rica table,.descricao-rica table{border-collapse:collapse;width:100%;margin:.3em 0}'
            . 'table .descricao-rica th,table .descricao-rica td,.descricao-rica th,.descricao-rica td'
            . '{border:.5px solid #bbb;padding:.2em .35em;font-size:.95em;vertical-align:top;text-align:left;background:none}'
            . 'table .descricao-rica th,.descricao-rica th{background:#f1f1f1;font-weight:bold}'
            . '.descricao-rica th p,.descricao-rica td p{margin:0}';
    }

    /** Para o PDF: o HTML limpo, ou o texto antigo com as mudanças de linha. */
    public static function paraImprimir(?string $texto): string
    {
        if ($texto === null || trim($texto) === '') {
            return '';
        }

        return self::eRica($texto) ? (string) self::limpar($texto) : nl2br(e($texto));
    }

    /**
     * Para a FACTURA: texto simples, com as listas e os parágrafos em linhas.
     * Cortado a `$maximo`, que é o que a linha de uma factura sempre levou.
     */
    public static function emTexto(?string $texto, int $maximo = 500): ?string
    {
        if ($texto === null) {
            return null;
        }

        if (self::eRica($texto)) {
            $t = (string) self::limpar($texto);
            $t = preg_replace('~<\s*br\s*/?>~i', "\n", $t);
            $t = preg_replace('~<\s*li\b[^>]*>~i', '• ', $t);
            $t = preg_replace('~</\s*(p|h[2-4]|li|blockquote|tr)\s*>~i', "\n", $t);
            $t = preg_replace('~</\s*(td|th)\s*>~i', '  ', $t);
            $t = preg_replace('~<\s*hr\s*/?>~i', "\n", $t);
            $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $linhas = array_values(array_filter(array_map('trim', preg_split('~\R~u', $t)), fn ($l) => $l !== ''));
            $texto = implode("\n", $linhas);
        }

        $texto = trim($texto);

        return mb_strlen($texto) > $maximo ? rtrim(mb_substr($texto, 0, $maximo - 1)) . '…' : $texto;
    }
}
