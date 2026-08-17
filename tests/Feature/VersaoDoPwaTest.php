<?php

namespace Tests\Feature;

use App\Http\Controllers\PwaController;
use Tests\TenantTestCase;

/**
 * O controlo de versao do PWA.
 *
 * Havia duas versoes independentes que nunca concordavam: a que se mostrava
 * vinha do changelog (escrita a mao) e a do service worker vinha de um resumo
 * do sw.js e do logotipo. Um deploy que mudasse o motor do PWA ou as paginas
 * do modo offline nao mexia em nenhuma das duas — o service worker nao se dava
 * por actualizado, e nao havia como saber que versao um aparelho corria.
 */
class VersaoDoPwaTest extends TenantTestCase
{
    private function versao(): string
    {
        \Illuminate\Support\Facades\Cache::forget('pwa.version');

        return app(PwaController::class)->buildVersion();
    }

    public function test_a_versao_muda_quando_o_motor_do_pwa_muda(): void
    {
        $antes = $this->versao();

        touch(public_path('js/pwa-invoicing.js'), time() + 60);

        $this->assertNotSame($antes, $this->versao(),
            'mexer no motor do PWA tem de gerar versao nova, senao o aparelho nao actualiza');
    }

    public function test_a_versao_muda_quando_uma_pagina_offline_muda(): void
    {
        $antes = $this->versao();

        touch(resource_path('views/invoicing/offline/pos.blade.php'), time() + 120);

        $this->assertNotSame($antes, $this->versao(),
            'as paginas offline sao o que fica em cache: mudar uma tem de contar');
    }

    /** Sem nada mexer, a versao e a mesma — senao cada visita invalidava a cache. */
    public function test_sem_mudancas_a_versao_e_estavel(): void
    {
        $this->assertSame($this->versao(), $this->versao());
    }

    /** O cabecalho do PWA mostra as duas: a release e a build. */
    public function test_o_cabecalho_mostra_a_release_e_a_build(): void
    {
        $html = $this->actingAs($this->user)->get('/invoicing/offline')->assertOk()->getContent();

        $this->assertStringContainsString(config('changelog.current'), $html, 'falta a release');
        $this->assertStringContainsString($this->versao(), $html, 'falta a build deste aparelho');
    }

    /** E o service worker servido leva essa mesma build. */
    public function test_o_service_worker_leva_a_mesma_build(): void
    {
        $sw = $this->get('/sw.js')->assertOk()->getContent();

        $this->assertStringContainsString("soserp-" . $this->versao(), $sw);
    }
}
