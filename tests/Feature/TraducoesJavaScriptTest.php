<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O dicionário que o PWA usa.
 *
 * É a peça mais frágil da tradução, e por uma razão concreta: **o POS
 * trabalha offline**. Se o dicionário faltar, ou vier na língua errada, ou
 * chegar depois do código que o usa, o caixa fica com o POS meio em inglês a
 * meio de uma venda — e sem rede não há como avisar ninguém nem corrigir.
 *
 * Era o `partials/js-traducoes` com um `window.__` à mão. Com o PWA em React o
 * tradutor é o mesmo dos outros ecrãs (`resources/js/i18n.ts`) e o dicionário
 * vai DENTRO da página, num `<script type="application/json">`, lido antes de
 * qualquer ecrã (`resources/js/pwa/dicionario.ts`). Estes testes são sobre a
 * MECÂNICA (existe, chega antes, é a língua certa, é o mesmo ficheiro dos
 * ecrãs) e não sobre o conteúdo, que o detector do TraducoesTest já cobre.
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

    /** O dicionário que veio na página, já lido. */
    private function dicionarioDaPagina(string $html): ?array
    {
        if (! preg_match('#<script type="application/json" id="pwa-dicionario">(.*?)</script>#s', $html, $m)) {
            return null;
        }

        return json_decode($m[1], true);
    }

    public function test_em_portugues_nao_se_emite_dicionario_nenhum(): void
    {
        $this->user->update(['locale' => null]);

        $html = $this->posOffline();

        // As chaves SÃO o português: mandar 200 KB de dicionário para dizer que
        // "Guardar" se diz "Guardar" é peso morto em cada página.
        $this->assertNull($this->dicionarioDaPagina($html));
        $this->assertStringContainsString('window.__reactLingua = "pt"', $html);
    }

    public function test_em_ingles_o_dicionario_vem_na_pagina(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->posOffline();
        $dicionario = $this->dicionarioDaPagina($html);

        $this->assertStringContainsString('window.__reactLingua = "en"', $html);
        $this->assertIsArray($dicionario, 'O dicionário tem de vir na página.');
        $this->assertSame('Cancel', $dicionario['Cancelar'] ?? null);
    }

    public function test_em_frances_tambem(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->posOffline();

        $this->assertStringContainsString('window.__reactLingua = "fr"', $html);
        $this->assertSame('Annuler', $this->dicionarioDaPagina($html)['Cancelar'] ?? null);
    }

    /**
     * O dicionário tem de chegar ANTES do código que o usa.
     *
     * Na página vem antes do pacote; e dentro do pacote, o módulo que o lê é o
     * PRIMEIRO `import` — os módulos ES correm pela ordem em que são
     * importados, e um `t()` à cabeça de um módulo que corresse antes ficava
     * em português para sempre.
     */
    public function test_o_dicionario_e_definido_antes_do_codigo_que_o_usa(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->posOffline();

        $dicionario = strpos($html, 'id="pwa-dicionario"');
        $pacote = strpos($html, '<script type="module"');

        $this->assertNotFalse($dicionario, 'O dicionário tem de estar na página.');

        if ($pacote !== false) {
            $this->assertLessThan($pacote, $dicionario, 'O dicionário tem de vir antes do pacote do PWA.');
        }

        preg_match_all('/^import\s.*$/m', file_get_contents(resource_path('js/pwa.tsx')), $imports);
        $this->assertSame("import './pwa/dicionario';", $imports[0][0] ?? null, 'O dicionário tem de ser o primeiro import do PWA.');
    }

    /**
     * O dicionário é o MESMO ficheiro dos ecrãs.
     *
     * Um segundo ficheiro de traduções só para o PWA seria mais um sítio para
     * desactualizar — e a divergência só apareceria com o POS já offline.
     */
    public function test_o_dicionario_sai_do_mesmo_ficheiro_dos_ecras(): void
    {
        $this->user->update(['locale' => 'en']);

        $naPagina = $this->dicionarioDaPagina($this->posOffline());
        $doDisco = json_decode(file_get_contents(base_path('lang/en.json')), true);

        $this->assertIsArray($naPagina);
        $this->assertSame(count($doDisco), count($naPagina),
            'O dicionário da página tem de ser o mesmo do lang/en.json — sem cópias nem subconjuntos.');
    }

    /** O tradutor é UM, e o PWA usa-o: nada de um `__` à parte a tapar o outro. */
    public function test_o_pwa_usa_o_tradutor_dos_ecras(): void
    {
        $this->assertStringContainsString('definirDicionario', file_get_contents(resource_path('js/pwa/dicionario.ts')));

        foreach (glob(resource_path('js/pwa/{,*/,*/*/}*.{ts,tsx}'), GLOB_BRACE) as $ficheiro) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bfunction\s+__n?\s*\(|\b(?:const|let|var)\s+__n?\s*=|window\.__n?\s*=/',
                file_get_contents($ficheiro),
                basename($ficheiro).' define um tradutor próprio — tapa o comum e cria duas traduções na mesma página.'
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
     * carrinho vazio em francês dizia "0 produits".
     */
    public function test_o_frances_usa_singular_no_zero(): void
    {
        $i18n = file_get_contents(resource_path('js/i18n.ts'));

        $this->assertStringContainsString("lingua() === 'fr' ? Math.abs(contagem) < 2", $i18n,
            'O tn tem de distinguir o francês — em francês, 0 e 1 levam singular.');
    }

    /** O dicionário do PWA não pode depender de nada que só exista online. */
    public function test_o_dicionario_nao_faz_pedidos_a_rede(): void
    {
        $leitor = file_get_contents(resource_path('js/pwa/dicionario.ts'));

        foreach (['fetch(', 'XMLHttpRequest', 'import(', 'axios', 'carregarDicionario'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $leitor,
                "O dicionário do PWA usa {$proibido} — o POS trabalha offline e um pedido à rede deixa-o sem traduções.");
        }
    }

    /**
     * O código do PWA muda de NOME quando muda de conteúdo.
     *
     * Era um `?v=` escrito à mão e depois tirado do `pwa_versao()`; um deploy
     * trocou o motor inteiro, o `?v=` ficou igual, e a correcção nunca chegou
     * aos aparelhos. O pacote leva hash no nome, e nenhum ficheiro nosso é
     * carregado pela casca sem versão.
     */
    public function test_o_codigo_do_pwa_muda_de_nome_quando_muda(): void
    {
        $casca = file_get_contents(resource_path('views/pwa/ecra.blade.php'));

        preg_match_all('/(?:src|href)="(\/[^"]+\.js)"/i', $casca, $m);

        $semVersao = array_values(array_filter($m[1], fn ($src) => ! str_starts_with($src, '/vendor/')));

        $this->assertSame([], $semVersao,
            "Ficheiros do PWA carregados sem versão:\n  ".implode("\n  ", $semVersao)
            ."\n\nO aparelho guarda-os em cache e nunca mais os vai buscar.");

        $this->assertStringContainsString("entryFileNames: 'pwa-[hash].js'", file_get_contents(base_path('vite.pwa.config.js')));
    }
}
