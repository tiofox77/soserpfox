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

    /**
     * A PROVA de que a versao segue os BYTES.
     *
     * Estes ensaios simulavam um deploy com `touch()` — mudavam a DATA do
     * ficheiro. Era exactamente a coisa que se descobriu nao funcionar: em
     * producao a data mexeu, a versao nao, e a correccao ficou parada no
     * servidor enquanto o Android continuava com o motor antigo. Um ensaio
     * que so mexe na data prova o mecanismo errado.
     *
     * Agora mexe-se na materia, num ficheiro temporario nosso — mexer nos
     * ficheiros verdadeiros do PWA com a suite a correr em 26 processos era
     * puxar-lhes o tapete aos outros ensaios.
     */
    public function test_a_versao_muda_quando_o_conteudo_muda(): void
    {
        $pwa = app(PwaController::class);
        $ficheiro = tempnam(sys_get_temp_dir(), 'pwa');

        try {
            file_put_contents($ficheiro, 'o motor de ontem');
            $antes = $pwa->assinaturaDe([$ficheiro]);

            file_put_contents($ficheiro, 'o motor de hoje');

            $this->assertNotSame($antes, $pwa->assinaturaDe([$ficheiro]),
                'mexer no motor do PWA tem de gerar versao nova, senao o aparelho nao actualiza');
        } finally {
            @unlink($ficheiro);
        }
    }

    /**
     * E o contrario, que e o defeito de origem: a data mudar sozinha NAO pode
     * chegar para dar versao nova. Era o que fazia o `filemtime()`, e por isso
     * a versao mudava em deploys que nao mudavam nada — e nao mudava em
     * deploys que mudavam tudo.
     */
    public function test_mexer_so_na_data_nao_da_versao_nova(): void
    {
        $pwa = app(PwaController::class);
        $ficheiro = tempnam(sys_get_temp_dir(), 'pwa');

        try {
            file_put_contents($ficheiro, 'a mesma coisa');
            $antes = $pwa->assinaturaDe([$ficheiro]);

            touch($ficheiro, time() + 3600);

            $this->assertSame($antes, $pwa->assinaturaDe([$ficheiro]),
                'a data mudou e o conteudo nao: os aparelhos nao tem nada para ir buscar');
        } finally {
            @unlink($ficheiro);
        }
    }

    /**
     * O QUE O DEFEITO DE HOJE ENSINOU: a lista do que conta nao pode ser
     * escrita a mao.
     *
     * Tinha la so o `pwa-invoicing.js`. Entretanto a pagina passou a carregar
     * o `pos-offline-ticket.js` e o `pwa-turno.js`, e nenhum contava: uma
     * correccao so ao turno saia com a MESMA versao, o service worker nao se
     * dava por actualizado, e ela nao chegava a quem vende.
     */
    public function test_todo_o_javascript_que_a_pagina_carrega_conta_para_a_versao(): void
    {
        $vigiados = app(PwaController::class)->ficheirosVigiados();

        $assets = PwaController::assetsDoLayout(
            file_get_contents(resource_path('views/layouts/pwa.blade.php'))
        );

        $this->assertNotEmpty($assets, 'o layout do PWA tem de carregar pelo menos um .js');

        $faltam = [];

        foreach ($assets as $src) {
            if (! in_array(public_path(ltrim($src, '/')), $vigiados, true)) {
                $faltam[] = $src;
            }
        }

        $this->assertSame([], $faltam,
            "Ficheiros que a pagina carrega mas que nao contam para a versao:\n  "
            .implode("\n  ", $faltam)
            ."\n\nMudar um deles sai com a versao de antes e nao chega aos aparelhos.");
    }

    public function test_a_versao_muda_quando_uma_pagina_offline_muda(): void
    {
        $this->assertContains(
            resource_path('views/invoicing/offline/pos.blade.php'),
            app(PwaController::class)->ficheirosVigiados(),
            'as paginas offline sao o que fica em cache: mudar uma tem de contar'
        );
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

        $this->assertStringContainsString('soserp-'.$this->versao(), $sw);
    }
}
