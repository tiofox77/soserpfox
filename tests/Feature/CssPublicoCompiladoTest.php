<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * AS PÁGINAS PÚBLICAS NÃO COMPILAM CSS NO BROWSER.
 *
 * A inicial carregava o `cdn.tailwindcss.com` e as outras o
 * `/vendor/js/tailwind.js`: 407 KB de compilador a correr no telemóvel de cada
 * visita antes de a página ter aspecto — pesa no LCP e no INP que o Google mede.
 * Passou a `public/css/publico.css`, gerado por `npm run css:publico` com a mesma
 * versão (3.4.17); a comparação de capturas das oito páginas saiu igual.
 */
class CssPublicoCompiladoTest extends TenantTestCase
{
    private const VISTAS = [
        'resources/views/landing/home.blade.php',
        'resources/views/components/modules-layout.blade.php',
        'resources/views/modules/partials/layout.blade.php',
        'resources/views/layouts/legal.blade.php',
        'resources/views/react/publico.blade.php',
    ];

    public function test_nenhuma_vista_publica_carrega_o_compilador(): void
    {
        foreach (self::VISTAS as $vista) {
            $fonte = file_get_contents(base_path($vista));

            $this->assertStringNotContainsString('cdn.tailwindcss.com', $fonte, "{$vista} voltou a compilar CSS no browser");
            $this->assertStringNotContainsString('/vendor/js/tailwind.js', $fonte, "{$vista} voltou a compilar CSS no browser");
            $this->assertStringContainsString("@include('partials.css-publico')", $fonte, "{$vista} ficou sem CSS");

            // No fim do <head>, como o runtime: antes do Font Awesome, o
            // `line-height` dele ganhava aos `text-4xl` dos ícones.
            $this->assertMatchesRegularExpression("/@include\('partials\.css-publico'\)\s*<\/head>/", $fonte, "{$vista}: o CSS tem de fechar o <head>");
        }
    }

    public function test_o_css_existe_e_traz_as_classes_das_paginas(): void
    {
        $css = file_get_contents(public_path('css/publico.css'));

        $this->assertGreaterThan(50_000, strlen($css), 'publico.css vazio — correr npm run css:publico');

        // Uma classe de cada sítio: vista, variante responsiva, cor com opacidade e ecrã React.
        foreach (['.bg-gradient-to-br', '.md\:grid-cols-2', '.bg-white\/10', '.-translate-y-1', '.tracking-\[0\.4em\]'] as $classe) {
            $this->assertStringContainsString($classe, $css, "falta {$classe} no publico.css — correr npm run css:publico");
        }
    }

    public function test_a_pagina_inicial_liga_o_css_com_versao(): void
    {
        $this->get('/modulos')->assertOk()
            ->assertSee('/css/publico.css?v=' . filemtime(public_path('css/publico.css')), false)
            ->assertDontSee('cdn.tailwindcss.com', false);
    }
}
