<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * O DICIONÁRIO QUE OS ECRÃS EM REACT USAM.
 *
 * A aplicação fala três línguas e o dicionário é o mesmo do Blade — os
 * `lang/en.json` e `lang/fr.json`, com a frase em português por chave. Ao
 * migrar os ecrãs para React, o `__()` ficou para trás e a facturação passou a
 * falar só português: é isso que isto repõe.
 *
 * PORTUGUÊS NÃO CARREGA DICIONÁRIO NENHUM. A chave já é a frase, portanto para
 * a esmagadora maioria das empresas não há um único byte a mais na página. Os
 * ficheiros pesam ~200 KB: mandá-los em todas as páginas para depois não serem
 * precisos era pagar a tradução a quem não a pediu.
 */
final class DicionarioDoReact
{
    /** As que têm ficheiro próprio. O português é a língua das chaves. */
    private const COM_DICIONARIO = ['en', 'fr'];

    public static function precisa(?string $lingua = null): bool
    {
        return in_array($lingua ?? app()->getLocale(), self::COM_DICIONARIO, true);
    }

    /** @return array<string, string> */
    public static function frases(?string $lingua = null): array
    {
        $lingua = $lingua ?? app()->getLocale();

        if (! self::precisa($lingua)) {
            return [];
        }

        $caminho = lang_path("{$lingua}.json");

        if (! File::exists($caminho)) {
            return [];
        }

        return json_decode(File::get($caminho), true) ?: [];
    }

    /**
     * A marca da versão, para o browser não voltar a descarregar o mesmo.
     *
     * Sai do ficheiro (data e tamanho): muda quando o dicionário muda, e um
     * deploy que não lhe toque não obriga ninguém a descarregá-lo outra vez.
     */
    public static function marca(?string $lingua = null): string
    {
        $lingua = $lingua ?? app()->getLocale();
        $caminho = lang_path("{$lingua}.json");

        if (! File::exists($caminho)) {
            return $lingua;
        }

        return $lingua . '-' . filemtime($caminho) . '-' . filesize($caminho);
    }
}
