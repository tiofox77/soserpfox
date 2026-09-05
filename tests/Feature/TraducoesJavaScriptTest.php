<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O dicionário que o JavaScript usa.
 *
 * É a peça mais frágil da tradução, e por uma razão concreta: **o POS
 * trabalha offline**. Se o dicionário faltar, ou vier na língua errada, ou
 * chegar depois dos scripts que o usam, o caixa fica com o POS meio em inglês
 * a meio de uma venda — e sem rede não há como avisar ninguém nem corrigir.
 *
 * Daí estes testes serem sobre a MECÂNICA (existe, chega antes, é a língua
 * certa, é o mesmo ficheiro dos ecrãs) e não sobre o conteúdo, que o detector
 * do TraducoesTest já cobre.
 */
class TraducoesJavaScriptTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        // O POS do PWA exige agora a permissão que o menu usa para o mostrar.
        $this->comPermissoes('invoicing.pos.access');
    }

    private function posOffline(): string
    {
        return $this->get('/invoicing/offline/pos')->assertOk()->getContent();
    }

    public function test_em_portugues_nao_se_emite_dicionario_nenhum(): void
    {
        $this->user->update(['locale' => null]);

        $html = $this->posOffline();

        // As chaves SÃO o português: mandar 27 KB de dicionário para dizer que
        // "Guardar" se diz "Guardar" é peso morto em cada página.
        $this->assertStringContainsString('window.SOS_TRADUCOES = {}', $html);
        $this->assertStringContainsString('window.SOS_LINGUA = "pt"', $html);
    }

    public function test_em_ingles_o_dicionario_vem_na_pagina(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->posOffline();

        $this->assertStringContainsString('window.SOS_LINGUA = "en"', $html);
        $this->assertStringContainsString('"Cancelar"', $html, 'O dicionário tem de trazer as chaves.');
        $this->assertStringContainsString('Cancel', $html);
    }

    public function test_em_frances_tambem(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->posOffline();

        $this->assertStringContainsString('window.SOS_LINGUA = "fr"', $html);
        $this->assertStringContainsString('Annuler', $html);
    }

    /**
     * O dicionário tem de chegar ANTES dos scripts que o usam.
     *
     * Se pwa-invoicing.js correr primeiro, window.__ ainda não existe e o POS
     * rebenta ao arrancar — offline, num ecrã em branco.
     */
    public function test_o_dicionario_e_definido_antes_dos_scripts_que_o_usam(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->posOffline();

        $dicionario = strpos($html, 'window.__ = function');
        $pwaJs = strpos($html, '/js/pwa-invoicing.js');
        $ticketJs = strpos($html, '/js/pos-offline-ticket.js');

        $this->assertNotFalse($dicionario, 'O window.__ tem de estar na página.');
        $this->assertNotFalse($pwaJs);

        $this->assertLessThan($pwaJs, $dicionario, 'O dicionário tem de vir antes do pwa-invoicing.js.');
        $this->assertLessThan($ticketJs, $dicionario, 'O dicionário tem de vir antes do pos-offline-ticket.js.');
    }

    /**
     * O dicionário do JS é o MESMO ficheiro dos ecrãs.
     *
     * Um segundo ficheiro de traduções só para JS seria mais um sítio para
     * desactualizar — e a divergência só apareceria com o POS já offline.
     */
    public function test_o_dicionario_sai_do_mesmo_ficheiro_dos_ecras(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->posOffline();
        $doDisco = json_decode(file_get_contents(base_path('lang/en.json')), true);

        preg_match('/window\.SOS_TRADUCOES = (\{.*?\});/s', $html, $m);

        $this->assertNotEmpty($m, 'Não encontrei o dicionário na página.');

        $naPagina = json_decode($m[1], true);

        $this->assertIsArray($naPagina);
        $this->assertSame(
            count($doDisco),
            count($naPagina),
            'O dicionário da página tem de ser o mesmo do lang/en.json — sem cópias nem subconjuntos.'
        );
    }

    /** O __ e o __n comportam-se como os do PHP. */
    public function test_o_dicionario_traz_as_duas_funcoes(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->posOffline();

        $this->assertStringContainsString('window.__ = function', $html);
        $this->assertStringContainsString('window.__n = function', $html);
    }

    /**
     * Nenhum JavaScript pode redefinir o __.
     *
     * Um `function __(...)` local dentro de um ficheiro de /js tapava o
     * global e a página passava a ter duas traduções diferentes conforme o
     * sítio de onde se chamasse.
     */
    public function test_nenhum_ficheiro_js_redefine_a_funcao(): void
    {
        foreach (glob(public_path('js/*.js')) as $ficheiro) {
            $conteudo = file_get_contents($ficheiro);

            $this->assertDoesNotMatchRegularExpression(
                '/\bfunction\s+__\s*\(|\b(?:const|let|var)\s+__\s*=/',
                $conteudo,
                basename($ficheiro).' redefine o __ — tapa o global e cria duas traduções na mesma página.'
            );
        }
    }

    /**
     * O zero não se comporta igual nas três línguas.
     *
     *   pt:  0 produtos   (plural)
     *   en:  0 products   (plural)
     *   fr:  0 produit    (SINGULAR)
     *
     * É o erro que passa despercebido porque em português está certo — um
     * carrinho vazio em francês dizia "0 produits". O JavaScript não traz as
     * regras de plural do Laravel consigo, portanto a regra está escrita à
     * mão no partial, e é aqui que fica presa.
     */
    public function test_o_frances_usa_singular_no_zero(): void
    {
        $partial = file_get_contents(resource_path('views/partials/js-traducoes.blade.php'));

        $this->assertStringContainsString(
            "window.SOS_LINGUA === 'fr'",
            $partial,
            'O __n tem de distinguir o francês — senão o zero sai em plural.'
        );

        $this->assertStringContainsString(
            'Math.abs(contagem) < 2',
            $partial,
            'Em francês, 0 e 1 levam singular.'
        );
    }

    /** As duas funções não podem depender de nada que só exista online. */
    public function test_o_dicionario_nao_faz_pedidos_a_rede(): void
    {
        $partial = file_get_contents(resource_path('views/partials/js-traducoes.blade.php'));

        foreach (['fetch(', 'XMLHttpRequest', 'import(', 'axios'] as $proibido) {
            $this->assertStringNotContainsString(
                $proibido,
                $partial,
                "O dicionário usa {$proibido} — o POS trabalha offline e um pedido à rede deixa-o sem traduções."
            );
        }
    }

    /**
     * A versão no URL dos .js tem de acompanhar o conteúdo.
     *
     * O service worker pré-cacheia `/js/pwa-invoicing.js?v=…`. Se o ficheiro
     * mudar e a versão não, quem já tem o PWA instalado continua com o
     * JavaScript antigo — português escrito por dentro, página traduzida por
     * fora — e não há nada no ecrã que o denuncie.
     *
     * ISTO CONFERIA UM NÚMERO ESCRITO À MÃO (`?v=14`), e o número à mão foi
     * exactamente o que falhou: um deploy trocou o motor do PWA inteiro, o
     * `?v=` ficou igual, e a correcção nunca chegou aos aparelhos. Passou a
     * sair do `pwa_versao()`, que é um resumo do conteúdo. O que aqui se
     * confere agora é o que resta poder correr mal: um ficheiro sem `?v=`
     * nenhum — esse fica em cache para sempre.
     */
    public function test_todos_os_js_do_pwa_levam_versao(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/pwa.blade.php'));

        preg_match_all('/(?:src|href)="(\/[^"]+\.js)"/i', $layout, $m);

        $semVersao = array_values(array_filter(
            $m[1],
            // O `/vendor/` são bibliotecas de terceiros fixas numa versão: o
            // nome do ficheiro já é a versão. O nosso código não tem essa
            // sorte — muda com o mesmo nome.
            fn ($src) => ! str_starts_with($src, '/vendor/')
        ));

        $this->assertSame([], $semVersao,
            "Ficheiros do PWA carregados sem `?v=`:\n  ".implode("\n  ", $semVersao)
            ."\n\nO aparelho guarda-os em cache e nunca mais os vai buscar.");
    }

    /**
     * E a versão é a MESMA na tag <script> e na lista de pré-carregamento.
     *
     * Divergirem é o erro fácil de cometer, porque estão a vinte linhas de
     * distância: o service worker pré-carrega um endereço e a página pede
     * outro — dois descarregamentos, e offline falta sempre um.
     */
    public function test_a_versao_dos_js_e_a_mesma_na_tag_e_no_precache(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/pwa.blade.php'));

        foreach (['pwa-invoicing.js', 'pos-offline-ticket.js', 'pwa-turno.js'] as $ficheiro) {
            preg_match_all('/'.preg_quote($ficheiro, '/').'\?v=([^\'"]+)/', $layout, $m);

            $this->assertGreaterThanOrEqual(
                2,
                count($m[1]),
                "{$ficheiro} devia aparecer na tag <script> e na lista de pré-carregamento."
            );

            $this->assertCount(
                1,
                array_unique($m[1]),
                "{$ficheiro} tem versões diferentes no layout: ".implode(', ', array_unique($m[1]))
                    .' — o service worker pré-carrega uma e a página usa outra.'
            );
        }
    }
}
