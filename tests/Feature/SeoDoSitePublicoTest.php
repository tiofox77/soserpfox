<?php

namespace Tests\Feature;

use App\Models\Restaurant\RestaurantSettings;
use App\Support\CasaPublica;
use App\Support\DadosEstruturados;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * O SEO DO SITE PÚBLICO — o que a auditoria de 2026-09-13 encontrou, preso.
 *
 *  - o sitemap levava o /login e o /register, que são `noindex` (duas ordens
 *    contrárias ao Google), e deixava de fora as páginas legais e as casas;
 *  - a carta de cada MESA era uma página indexável, duplicada da carta;
 *  - a carta, a marcação e as reservas de uma empresa DESACTIVADA continuavam
 *    no ar, a aceitar pedidos;
 *  - /offline, os ecrãs do PWA e a aplicação diziam «index» ao Google;
 *  - a lista dos módulos não tinha canónico nem dados estruturados.
 */
class SeoDoSitePublicoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(\App\Http\Controllers\SitemapController::CHAVE);
        CasaPublica::esquecer();
    }

    private function cartaPublicada(): RestaurantSettings
    {
        $this->comModulo('restaurant');

        RestaurantSettings::forTenant($this->tenant->id);
        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update([
            'menu_slug' => 'casa-' . uniqid(),
            'online_menu_enabled' => true,
            'menu_title' => 'Tasca do Mussulo',
            'menu_description' => 'Grelhados e marisco à beira da baía.',
        ]);

        return RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
    }

    /** @return list<string> */
    private function enderecosDoSitemap(): array
    {
        $r = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', $r->headers->get('Content-Type'));

        $xml = simplexml_load_string($r->getContent());
        $this->assertNotFalse($xml, 'o sitemap é XML válido (nada antes do <?xml)');

        return array_map(fn ($u) => (string) $u->loc, iterator_to_array($xml->url, false));
    }

    private function semRaiz(string $url): string
    {
        return substr($url, strlen(DadosEstruturados::raiz())) ?: '/';
    }

    public function test_o_sitemap_so_leva_paginas_indexaveis_e_todas_abrem(): void
    {
        $enderecos = $this->enderecosDoSitemap();
        $caminhos = array_map(fn ($u) => $this->semRaiz($u), $enderecos);

        $this->assertNotContains('/login', $caminhos, 'o login é noindex');
        $this->assertNotContains('/register', $caminhos, 'o registo é noindex');
        $this->assertContains('/privacidade', $caminhos);
        $this->assertContains('/termos', $caminhos);
        $this->assertContains('/modulos/vendas', $caminhos);

        foreach ($enderecos as $url) {
            $this->assertStringNotContainsString('?', $url, "{$url}: nada de query string no sitemap");
        }

        // Cada página do produto abre, e nenhuma pede para não ser indexada.
        foreach (array_filter($caminhos, fn ($c) => $c === '/' || str_starts_with($c, '/modulos') || in_array($c, ['/termos', '/privacidade'], true)) as $c) {
            $pagina = $this->get($c)->assertOk()->getContent();
            $this->assertStringNotContainsString('noindex', $pagina, "{$c} está no sitemap e diz noindex");
        }
    }

    public function test_a_carta_publicada_entra_no_sitemap_e_sai_quando_a_empresa_e_desactivada(): void
    {
        $d = $this->cartaPublicada();
        $url = DadosEstruturados::raiz() . '/menu/' . $d->menu_slug;

        $this->assertContains($url, $this->enderecosDoSitemap());
        $this->get('/menu/' . $d->menu_slug)->assertOk();

        $this->tenant->forceFill(['is_active' => false])->save();
        CasaPublica::esquecer();
        // Quem vê isto é um estranho, sem sessão: o utilizador do ensaio, com a
        // empresa desactivada, era mandado para a página do bloqueio.
        \Illuminate\Support\Facades\Auth::logout();
        Cache::forget(\App\Http\Controllers\SitemapController::CHAVE);

        $this->assertNotContains($url, $this->enderecosDoSitemap(), 'empresa desactivada não aparece');
        $this->get('/menu/' . $d->menu_slug)->assertNotFound();
        $this->postJson('/api/publico/restaurante/' . $d->menu_slug . '/pedido', ['escolhas' => [['id' => 1, 'quantidade' => 1]]])
            ->assertNotFound();
    }

    public function test_a_carta_da_mesa_nao_se_indexa_e_aponta_para_a_carta(): void
    {
        $d = $this->cartaPublicada();
        $canonico = '<link rel="canonical" href="' . DadosEstruturados::raiz() . '/menu/' . $d->menu_slug . '">';

        $this->get('/menu/' . $d->menu_slug)->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee($canonico, false)
            ->assertSee('<title>Menu — Tasca do Mussulo</title>', false);

        $this->get('/menu/' . $d->menu_slug . '/M7')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee($canonico, false);
    }

    public function test_a_carta_tem_dados_estruturados_com_valores_certos(): void
    {
        $d = $this->cartaPublicada();
        $this->tenant->forceFill(['country' => 'Portugal', 'address' => 'Rua da Missão, 12', 'city' => 'Luanda'])->save();

        $html = $this->get('/menu/' . $d->menu_slug)->assertOk()->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $casa = json_decode($m[1] ?? 'null', true)['@graph'][0] ?? null;

        $this->assertSame('Restaurant', $casa['@type']);
        $this->assertSame(DadosEstruturados::raiz() . '/menu/' . $d->menu_slug . '#casa', $casa['@id']);
        $this->assertSame('Tasca do Mussulo', $casa['name']);
        $this->assertSame('Grelhados e marisco à beira da baía.', $casa['description']);
        $this->assertSame('Rua da Missão, 12', $casa['address']['streetAddress']);
        $this->assertSame('AO', $casa['address']['addressCountry'], 'o DEFAULT Portugal da ficha não passa para o Google');
        $this->assertArrayNotHasKey('aggregateRating', $casa, 'não há avaliações na página');
        $this->assertArrayNotHasKey('telephone', $casa, 'sem contacto público, não se inventa');
    }

    public function test_as_paginas_que_nao_respondem_a_uma_pesquisa_dizem_noindex(): void
    {
        $this->get('/offline')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/pagina-que-nao-existe-' . uniqid())->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_a_lista_dos_modulos_tem_canonico_e_a_itemlist_dos_cartoes(): void
    {
        $html = $this->get('/modulos')->assertOk()
            ->assertSee('<link rel="canonical" href="' . DadosEstruturados::raiz() . '/modulos">', false)
            ->getContent();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $grafo = json_decode($m[1] ?? 'null', true)['@graph'] ?? [];
        $lista = collect($grafo)->firstWhere('@type', 'ItemList');

        $this->assertNotNull($lista);
        $this->assertSame(count($lista['itemListElement']), $lista['numberOfItems']);

        foreach ($lista['itemListElement'] as $item) {
            $slug = substr($item['url'], strlen(DadosEstruturados::raiz()));
            $this->assertStringContainsString('href="' . $slug . '"', $html, "{$slug} está na ItemList e não na página");
        }
    }
}
