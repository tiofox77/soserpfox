<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * HTML ESCRITO POR UTILIZADORES, LIMPO ANTES DE SER MOSTRADO OU IMPRESSO.
 *
 * O bloco de texto dos modelos de proposta saía tal e qual: um
 * `<img src=x onerror=…>` corria na sessão de quem pré-visualizasse o modelo, e
 * uma imagem com endereço externo punha o gerador de PDF a visitar esse endereço
 * (auditoria de segurança de 2026-09-13). Fica a formatação — títulos, listas,
 * negrito, tabelas, cores —; sai tudo o que corre código ou vai buscar coisas.
 */
final class HtmlSeguro
{
    /** Etiquetas que ficam (com os filhos). As outras desembrulham-se, e as perigosas saem com tudo. */
    private const ETIQUETAS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup', 'small', 'mark', 'span', 'div',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'hr', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'colgroup', 'col', 'a', 'img', 'font',
    ];

    /** Estas saem inteiras — o conteúdo delas não é texto de ninguém. */
    private const PERIGOSAS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'audio', 'video', 'source',
    ];

    private const ATRIBUTOS = ['style', 'class', 'align', 'valign', 'colspan', 'rowspan', 'width', 'height', 'color', 'size', 'face', 'title', 'alt', 'href', 'src', 'target'];

    public static function limpar(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="html-seguro-raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $dom->getElementById('html-seguro-raiz');

        if (! $raiz) {
            return e(strip_tags($html));
        }

        self::percorrer($raiz);

        $saida = '';
        foreach (iterator_to_array($raiz->childNodes) as $filho) {
            $saida .= $dom->saveHTML($filho);
        }

        return $saida;
    }

    private static function percorrer(DOMNode $no): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho->nodeType === XML_COMMENT_NODE || $filho->nodeType === XML_PI_NODE) {
                $no->removeChild($filho);

                continue;
            }

            if (! $filho instanceof DOMElement) {
                continue;
            }

            $nome = strtolower($filho->nodeName);

            if (in_array($nome, self::PERIGOSAS, true)) {
                $no->removeChild($filho);

                continue;
            }

            self::percorrer($filho);

            if (! in_array($nome, self::ETIQUETAS, true)) {
                // Desembrulha: fica o texto, sai a etiqueta desconhecida.
                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }
                $no->removeChild($filho);

                continue;
            }

            self::limparAtributos($filho, $nome);
        }
    }

    private static function limparAtributos(DOMElement $el, string $nome): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $chave = strtolower($attr->nodeName);
            $valor = trim((string) $attr->nodeValue);
            $fica = in_array($chave, self::ATRIBUTOS, true);

            if ($fica && $chave === 'href') {
                $fica = $nome === 'a' && (bool) preg_match('#^(https?:|mailto:|tel:|\#)#i', $valor);
            }

            if ($fica && $chave === 'src') {
                // Só imagens embutidas: um endereço externo punha o gerador de PDF a visitá-lo.
                $fica = $nome === 'img' && (bool) preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $valor);
            }

            if ($fica && $chave === 'style') {
                $fica = ! preg_match('#url\s*\(|expression\s*\(|javascript:|@import|behavior\s*:|-moz-binding#i', $valor);
            }

            if ($fica && $chave === 'target') {
                $el->setAttribute('rel', 'noopener noreferrer');
            }

            if (! $fica) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        if ($nome === 'img' && ! $el->hasAttribute('src')) {
            $el->parentNode?->removeChild($el);
        }
    }
}
