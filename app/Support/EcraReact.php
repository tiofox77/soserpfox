<?php

namespace App\Support;

use Closure;

/**
 * A ACÇÃO DE ROTA QUE SERVE UM ECRÃ EM REACT.
 *
 * Uma página normal do layout de sempre: menu, cabeçalho e permissões
 * continuam a vir do Laravel; só o miolo é React. Os parâmetros da rota
 * (`{id}`, `{tipo}`) chegam ao ecrã como props, junto com os que a rota
 * fixar — é assim que `/sales/invoices/{id}/edit` abre a factura certa.
 */
final class EcraReact
{
    /**
     * O `$aoAbrir` corre a cada pedido e junta props que só se sabem então —
     * a factura que vem no endereço, por exemplo. As fixas, essas, ficam no
     * `$props` e não voltam a ser calculadas.
     */
    public static function pagina(string $ecra, string $titulo, array $props = [], ?Closure $aoAbrir = null): Closure
    {
        return function () use ($ecra, $titulo, $props, $aoAbrir) {
            $daRota = collect(request()->route()?->parameters() ?? [])
                ->map(fn ($v) => is_string($v) && ctype_digit($v) ? (int) $v : $v)
                ->all();

            return view('react.ecra', [
                'ecra' => $ecra,
                // O cabeçalho da página é desenhado pelo Laravel e traduz-se
                // como o resto do layout. Sem o `__()`, quem trabalha em
                // inglês via o menu traduzido e o título da página em
                // português — e é o título que se lê primeiro.
                'titulo' => __($titulo),
                'subtitulo' => '',
                'props' => array_merge($daRota, $props, $aoAbrir ? $aoAbrir() : []),
            ]);
        };
    }
}
