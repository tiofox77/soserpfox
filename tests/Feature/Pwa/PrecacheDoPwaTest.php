<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * O service worker tem de pré-guardar EXACTAMENTE o que o PWA pede.
 *
 * O DEFEITO QUE ISTO FECHA: o layout do PWA passou a carregar tudo de
 * `/vendor/` (local) e o precache do service worker ficou a apontar para os
 * CDN antigos — unpkg, cdnjs, cdn.tailwindcss.com — que a aplicação já não
 * pede. Num aparelho acabado de instalar e sem rede: sem Tailwind não há
 * desenho, sem Alpine o ecrã não responde, e sem o Dexie o motor offline nem
 * arranca (o pwa-invoicing.js começa com `if (typeof Dexie === 'undefined')
 * return`). Parecia que a lógica offline não existia — existia, mas nunca
 * chegava a correr.
 *
 * O erro é invisível: o `cache.add()` falha, o `catch` escreve um aviso na
 * consola do service worker (que ninguém abre) e a instalação segue como se
 * estivesse tudo bem. Só se descobre sem rede, que é o pior momento possível.
 */
class PrecacheDoPwaTest extends TenantTestCase
{
    /**
     * O service worker COMO ELE É SERVIDO, e não o ficheiro em bruto.
     *
     * O `?v=` deixou de ser escrito à mão nos dois sítios: o layout usa
     * `pwa_versao()` e o controlador reescreve o do sw.js ao servi-lo. Ler o
     * ficheiro em bruto comparava a versão de ontem com a de hoje e dizia que
     * o precache não cobria nada — quando o problema era o ensaio estar a
     * olhar para o sítio errado. Servido contra renderizado é o que o
     * aparelho vê, e é a única comparação que prova alguma coisa.
     */
    private function sw(): string
    {
        return $this->get('/sw.js')->assertOk()->getContent();
    }

    private function layout(): string
    {
        return view('layouts.pwa', ['title' => 'ensaio'])->render();
    }

    /** Os URLs que o layout do PWA pede, tal e qual. */
    private function assetsDoLayout(): array
    {
        preg_match_all('/(?:src|href)="(\/[^"]+\.(?:js|css)(?:\?[^"]*)?)"/', $this->layout(), $m);

        return array_values(array_unique($m[1]));
    }

    /** Os URLs listados no PRECACHE_URLS do service worker. */
    private function precache(): array
    {
        preg_match('/const PRECACHE_URLS = \[(.*?)\];/s', $this->sw(), $m);
        $this->assertNotEmpty($m[1] ?? '', 'PRECACHE_URLS não encontrado no sw.js');

        preg_match_all("/'([^']+)'/", $m[1], $u);

        return $u[1];
    }

    /** A PROVA: tudo o que o layout pede está pré-guardado, letra a letra. */
    public function test_o_precache_cobre_todos_os_assets_do_layout(): void
    {
        $emFalta = array_diff($this->assetsDoLayout(), $this->precache());

        $this->assertEmpty(
            $emFalta,
            "Assets que o PWA carrega mas o service worker não pré-guarda:\n  "
            .implode("\n  ", $emFalta)
            ."\n\nSem rede, o aparelho fica sem eles."
        );
    }

    /** E o contrário: nada de CDN, que é de onde vinha o problema. */
    public function test_o_precache_nao_depende_de_cdn(): void
    {
        $deCdn = array_filter($this->precache(), fn ($u) => str_starts_with($u, 'http'));

        $this->assertEmpty(
            $deCdn,
            "O precache não pode depender de CDN — offline não há CDN nenhum:\n  "
            .implode("\n  ", $deCdn)
        );
    }

    /** O layout também não: um CDN no layout é um buraco offline. */
    public function test_o_layout_do_pwa_nao_carrega_nada_de_cdn(): void
    {
        preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', $this->layout(), $m);

        // O que aponta para a própria aplicação não é «de fora». O layout
        // renderizado tem endereços absolutos gerados pelo `url()` — ícones,
        // manifesto, a saída do modo offline. De CDN só conta outro domínio.
        $daCasa = rtrim(config('app.url'), '/');

        $deFora = array_values(array_filter(
            $m[1],
            fn ($u) => ! str_starts_with($u, $daCasa.'/') && $u !== $daCasa
        ));

        $this->assertEmpty(
            $deFora,
            "O layout do PWA carrega de fora:\n  ".implode("\n  ", $deFora)
        );
    }

    /**
     * Os quatro ficheiros sem os quais não há offline nenhum.
     *
     * Escritos à mão de propósito: se alguém trocar o Dexie por outra coisa,
     * este teste obriga a pensar em vez de deixar passar.
     */
    public function test_o_motor_offline_esta_pre_guardado(): void
    {
        $precache = $this->precache();

        foreach ([
            '/vendor/js/dexie.min.js' => 'sem Dexie o motor offline nem arranca',
            '/vendor/js/alpine.min.js' => 'sem Alpine o ecrã não responde',
            '/vendor/js/tailwind.js' => 'sem Tailwind não há desenho nenhum',
            '/vendor/css/fontawesome.min.css' => 'sem os ícones o POS fica ilegível',
        ] as $ficheiro => $porque) {
            $this->assertContains($ficheiro, $precache, "Falta {$ficheiro} — {$porque}.");
        }
    }

    /** O código da aplicação offline também: sem ele o ecrã não sabe fazer nada. */
    public function test_o_codigo_da_aplicacao_offline_esta_pre_guardado(): void
    {
        $precache = $this->precache();
        $comApp = array_filter($precache, fn ($u) => str_contains($u, 'pwa-invoicing.js'));

        $this->assertNotEmpty($comApp, 'O pwa-invoicing.js tem de estar pré-guardado.');
    }

    /** Os ficheiros pré-guardados existem mesmo no disco. */
    public function test_os_ficheiros_pre_guardados_existem(): void
    {
        foreach ($this->precache() as $url) {
            if (str_starts_with($url, 'http')) {
                continue;
            }

            // A página /offline e os ícones são servidos por rota, não por
            // ficheiro — testam-se por HTTP mais abaixo.
            if (! preg_match('/\.(js|css|png|woff2?)$/', strtok($url, '?'))) {
                continue;
            }

            $caminho = public_path(ltrim(strtok($url, '?'), '/'));

            $this->assertFileExists($caminho, "O precache aponta para um ficheiro que não existe: {$url}");
        }
    }

    /** As páginas do PWA pré-guardadas correspondem a rotas reais. */
    public function test_as_paginas_pre_guardadas_sao_rotas_reais(): void
    {
        preg_match('/const PRECACHE_PAGINAS = \[(.*?)\];/s', $this->sw(), $m);
        $this->assertNotEmpty($m[1] ?? '', 'PRECACHE_PAGINAS não encontrado no sw.js');

        preg_match_all("/'([^']+)'/", $m[1], $u);
        $this->assertNotEmpty($u[1], 'Nenhuma página do PWA é pré-guardada.');

        $rotas = collect(\Route::getRoutes())->map(fn ($r) => '/'.ltrim($r->uri(), '/'))->all();

        foreach ($u[1] as $pagina) {
            $this->assertContains(
                $pagina,
                $rotas,
                "O service worker pré-guarda '{$pagina}', que não é uma rota."
            );
        }
    }

    /**
     * As páginas NÃO podem ser guardadas com `cache.add()`.
     *
     * O `cache.add()` segue os redireccionamentos e guarda o que vier ao fim.
     * Sem sessão iniciada, `/invoicing/offline/pos` responde 302 para `/login`
     * — e o que ficava guardado debaixo do URL do POS era o ECRÃ DE LOGIN.
     * Offline, abrir o POS mostrava um formulário de entrada que não tem como
     * funcionar sem rede: pior do que a página de offline, porque parece que a
     * aplicação está a pedir credenciais e a recusá-las.
     */
    public function test_as_paginas_nao_sao_guardadas_com_cache_add(): void
    {
        $sw = $this->sw();

        $this->assertStringContainsString(
            'async function guardarPagina',
            $sw,
            'As páginas têm de passar por guardarPagina(), que verifica o desvio.'
        );

        $this->assertStringContainsString(
            'resposta.redirected',
            $sw,
            'guardarPagina() tem de recusar uma resposta desviada (login).'
        );

        // E o install não pode voltar a usar cache.add para as páginas.
        preg_match('/PRECACHE_PAGINAS\.map\((.*?)\)\)/s', $sw, $m);
        $this->assertStringNotContainsString(
            'cache.add',
            $m[1] ?? '',
            'PRECACHE_PAGINAS não pode usar cache.add — segue redireccionamentos.'
        );
    }

    /** O POS é a razão de a aplicação existir — tem de estar lá. */
    public function test_o_pos_offline_esta_pre_guardado(): void
    {
        $this->assertStringContainsString(
            "'/invoicing/offline/pos'",
            $this->sw(),
            'O POS offline tem de ser pré-guardado: é para isso que a aplicação serve.'
        );
    }
}
