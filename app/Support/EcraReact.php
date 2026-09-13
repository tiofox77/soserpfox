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
        return self::servir('react.ecra', $ecra, $titulo, $props, $aoAbrir);
    }

    /**
     * O MESMO, NO PAINEL DA PLATAFORMA.
     *
     * O superadmin tem layout próprio — outro menu, outras cores, outros nomes
     * de secção — e é por isso que precisa de uma porta sua. O que muda é só a
     * vista que embrulha a ilha; as props e o título vêm pelo mesmo caminho.
     */
    public static function plataforma(string $ecra, string $titulo, array $props = [], ?Closure $aoAbrir = null): Closure
    {
        return self::servir('react.ecra-superadmin', $ecra, $titulo, $props, $aoAbrir);
    }

    /**
     * NO PORTAL DO CLIENTE — o layout do portal, com o menu do cliente e sem o
     * da empresa.
     */
    public static function cliente(string $ecra, string $titulo, array $props = []): Closure
    {
        return self::servir('react.ecra-cliente', $ecra, $titulo, $props, null);
    }

    /** A entrada do portal: página sem menu, só o ecrã. */
    public static function entradaCliente(string $ecra, string $titulo, array $props = []): Closure
    {
        // O logótipo e o nome saem da configuração a cada pedido: a página de
        // entrada não tem layout que os desenhe.
        return self::servir('react.pagina-solta', $ecra, $titulo, $props, fn () => ['logo' => app_logo(), 'nome' => app_name()], [
            'descricao' => __('Acesse sua área exclusiva do cliente para visualizar faturas, eventos e documentos.'),
        ]);
    }

    /**
     * UMA PÁGINA SOLTA — sem menu nem sessão de empresa: o registo de uma conta
     * nova, o assistente de 1.ª utilização. O ecrã desenha a página inteira.
     */
    public static function solta(string $ecra, string $titulo, array $props = [], array $daVista = []): Closure
    {
        return self::servir('react.pagina-solta', $ecra, $titulo, $props, fn () => ['logo' => app_logo(), 'nome' => app_name()], $daVista + ['descricao' => '']);
    }

    private static function servir(string $vista, string $ecra, string $titulo, array $props, ?Closure $aoAbrir, array $daVista = []): Closure
    {
        return function () use ($vista, $ecra, $titulo, $props, $aoAbrir, $daVista) {
            $daRota = collect(request()->route()?->parameters() ?? [])
                ->map(fn ($v) => is_string($v) && ctype_digit($v) ? (int) $v : $v)
                ->all();

            return view($vista, $daVista + [
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
