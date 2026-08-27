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
    private function sw(): string
    {
        return file_get_contents(resource_path('pwa/sw.js'));
    }

    private function layout(): string
    {
        return file_get_contents(resource_path('views/layouts/pwa.blade.php'));
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
            . implode("\n  ", $emFalta)
            . "\n\nSem rede, o aparelho fica sem eles."
        );
    }

    /** E o contrário: nada de CDN, que é de onde vinha o problema. */
    public function test_o_precache_nao_depende_de_cdn(): void
    {
        $deCdn = array_filter($this->precache(), fn ($u) => str_starts_with($u, 'http'));

        $this->assertEmpty(
            $deCdn,
            "O precache não pode depender de CDN — offline não há CDN nenhum:\n  "
            . implode("\n  ", $deCdn)
        );
    }

    /** O layout também não: um CDN no layout é um buraco offline. */
    public function test_o_layout_do_pwa_nao_carrega_nada_de_cdn(): void
    {
        preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', $this->layout(), $m);

        $this->assertEmpty(
            $m[1],
            "O layout do PWA carrega de fora:\n  " . implode("\n  ", $m[1])
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
            '/vendor/js/dexie.min.js'      => 'sem Dexie o motor offline nem arranca',
            '/vendor/js/alpine.min.js'     => 'sem Alpine o ecrã não responde',
            '/vendor/js/tailwind.js'       => 'sem Tailwind não há desenho nenhum',
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
            if (!preg_match('/\.(js|css|png|woff2?)$/', strtok($url, '?'))) {
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

        $rotas = collect(\Route::getRoutes())->map(fn ($r) => '/' . ltrim($r->uri(), '/'))->all();

        foreach ($u[1] as $pagina) {
            $this->assertContains(
                $pagina,
                $rotas,
                "O service worker pré-guarda '{$pagina}', que não é uma rota."
            );
        }
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
