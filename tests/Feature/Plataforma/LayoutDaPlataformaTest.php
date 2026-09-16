<?php

namespace Tests\Feature\Plataforma;

use Tests\TenantTestCase;

/**
 * O LAYOUT DO PAINEL DA PLATAFORMA SEM ALPINE, JQUERY NEM TOASTR.
 *
 * A barra lateral era Blade com Alpine («Perfil» e «Configurações» apontavam
 * para «#»), os recados da sessão saíam por toastr e o Chart.js descia em todas
 * as páginas sem ninguém o usar. É agora a mesma casca da aplicação.
 */
class LayoutDaPlataformaTest extends TenantTestCase
{
    private function props(string $html, string $peca): array
    {
        $this->assertSame(1, preg_match('/data-peca="'.preg_quote($peca, '/').'"\s+data-props="([^"]*)"/', $html, $m), "falta a peça {$peca}");

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    public function test_a_casca_da_plataforma_em_react(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        $html = $this->get('/home')->assertOk()->getContent();

        foreach (['alpine.min.js', 'jquery', 'toastr', 'chart.min.js', 'x-data', '@click', 'href="#"'] as $antigo) {
            $this->assertStringNotContainsString($antigo, $html, "o layout da plataforma ainda traz {$antigo}");
        }

        $menu = $this->props($html, 'casca')['menu'];

        $this->assertNull($menu['suporte'], 'a plataforma não pede suporte a si própria');
        $this->assertSame([], $menu['grupos']);
        $this->assertSame(['Principal', 'Comercial', 'Comunicação', 'Sistema', 'Configuração'], array_column($menu['superadmin'], 'titulo'));

        $urls = array_merge(...array_map(fn ($s) => array_column($s['entradas'], 'url'), $menu['superadmin']));
        $this->assertCount(26, $urls, 'as 24 áreas do painel de sempre, as cópias de segurança e os revendedores');
        $this->assertContains(route('superadmin.copias'), $urls);
        $this->assertContains(route('superadmin.revendedores'), $urls);
        $this->assertContains(route('superadmin.aparelhos-pwa'), $urls);

        // O menu do utilizador leva a algum lado.
        foreach ($menu['utilizador']['ligacoes'] as $l) {
            $this->assertStringStartsWith('http', $l['url']);
        }

        $this->assertStringContainsString('data-peca="casca/alternar"', $html);
        $this->assertSame(app()->getLocale(), $this->props($html, 'casca/lingua')['actual']);
    }

    public function test_os_recados_da_sessao_chegam_como_avisos(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        $html = $this->withSession(['error' => 'Não foi possível guardar.', 'message' => 'Guardado.'])
            ->get('/home')->assertOk()->getContent();

        $this->assertEqualsCanonicalizing([
            ['tipo' => 'erro', 'texto' => 'Não foi possível guardar.'],
            ['tipo' => 'ok', 'texto' => 'Guardado.'],
        ], $this->props($html, 'casca/sistema')['recados']);
    }

    /** Nos ecrãs da aplicação também: o `->with('error')` de um redirect para um ecrã React ninguém o via. */
    public function test_na_aplicacao_o_recado_de_um_redirect_chega(): void
    {
        $html = $this->withSession(['error' => 'Dê um endereço à carta antes de gerar os QR.'])
            ->get('/my-account')->assertOk()->getContent();

        $this->assertSame([['tipo' => 'erro', 'texto' => 'Dê um endereço à carta antes de gerar os QR.']], $this->props($html, 'casca/sistema')['recados']);
    }

    public function test_na_pagina_inicial_o_recado_nao_sai_a_dobrar(): void
    {
        $html = $this->withSession(['success' => 'Empresa criada.'])->get('/home')->assertOk()->getContent();

        $this->assertSame([], $this->props($html, 'casca/sistema')['recados'], 'o ecrã inicial já o mostra na faixa');
    }
}
