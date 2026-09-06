<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Lote 4 — stock, catálogo e dados-mestre nas três línguas.
 *
 * O detector do TraducoesTest garante que toda a cadeia embrulhada TEM
 * tradução. Não garante que ela chegue ao ecrã: uma chave pode estar no
 * dicionário e a linha nunca ser desenhada, ou ser desenhada a partir de
 * outro sítio que ficou por embrulhar. Estes testes olham para o HTML.
 *
 * O embrulho deste lote foi feito por script — 800 e tal cadeias em 28
 * ficheiros — e é isso que torna estes testes necessários e não opcionais: um
 * script que corre bem em 27 ficheiros e estraga o 28.º não dá erro nenhum a
 * dizê-lo. Aconteceu: partiu dois @foreach por não saber contar parênteses.
 */
class LoteStockLinguaTest extends TenantTestCase
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

        foreach ([
            'invoicing.products.view',
            'invoicing.warehouses.view',
            'invoicing.categories.view',
            'invoicing.brands.view',
            'invoicing.clients.view',
            'invoicing.suppliers.view',
            'invoicing.stock.view',
        ] as $nome) {
            $this->user->givePermissionTo(
                \Spatie\Permission\Models\Permission::firstOrCreate(
                    ['name' => $nome, 'guard_name' => 'web']
                )
            );
        }
    }

    /**
     * A página como se lê: sem o dicionário embutido (que traz as chaves
     * portuguesas), sem comentários HTML e com os \uXXXX do @json desfeitos.
     * Sem isto, um assertDontSee sobre português falha em páginas
     * perfeitamente traduzidas — ver LoteMenuLinguaTest, onde isto foi
     * descoberto à força.
     */
    private function comoSeLe(string $rota): string
    {
        $html = $this->get($rota)->assertOk()->getContent();

        $html = preg_replace('/window\.SOS_TRADUCOES = \{.*?\};/s', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        return preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            fn ($m) => mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE'),
            $html
        );
    }

    /**
     * Os seis ecrãs do lote — hoje todos em React.
     *
     * As cadeias do miolo mudaram-se para os `.tsx` e já não passam por
     * `__()`: o que continua a poder medir-se por HTTP é a página abrir e o
     * LAYOUT à volta falar a língua escolhida. A dívida do miolo está marcada
     * no `TraducoesTest::test_os_ecras_em_react_ainda_nao_falam_as_tres_linguas`.
     */
    public static function ecras(): array
    {
        return [
            'produtos'     => ['/invoicing/products'],
            'armazéns'     => ['/invoicing/warehouses'],
            'categorias'   => ['/invoicing/categories'],
            'marcas'       => ['/invoicing/brands'],
            'clientes'     => ['/invoicing/clients'],
            'fornecedores' => ['/invoicing/suppliers'],
        ];
    }

    /** @dataProvider ecras */
    public function test_o_ecra_fala_ingles(string $rota): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->comoSeLe($rota);

        $this->assertStringContainsString('Session expired', $html);
        $this->assertStringContainsString('Support', $html);
    }

    /** @dataProvider ecras */
    public function test_o_ecra_fala_frances(string $rota): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($rota);

        $this->assertStringContainsString('Session expirée', $html);
        $this->assertStringNotContainsString('Session expired', $html);
    }

    /** @dataProvider ecras */
    public function test_sem_escolha_o_ecra_fala_portugues(string $rota): void
    {
        $html = $this->comoSeLe($rota);

        $this->assertStringContainsString('Sessão expirada', $html);
        $this->assertStringNotContainsString('Session expired', $html);
    }

    /**
     * As mensagens dos componentes saem na língua do utilizador.
     *
     * Não estão no Blade, portanto quem revê um ecrã traduzido não as vê lá —
     * só aparecem depois de se carregar num botão. É onde uma falta sobrevive
     * mais tempo sem ser notada.
     */
    public function test_as_mensagens_dos_componentes_saem_traduzidas(): void
    {
        $this->user->update(['locale' => 'en']);
        app()->setLocale('en');

        $this->assertSame('Warehouse created successfully!', __('Armazém criado com sucesso!'));
        $this->assertSame('Product deleted. You can restore it from the "Deleted" filter.', __('Produto eliminado. Pode restaurá-lo no filtro "Eliminados".'));

        app()->setLocale('fr');

        $this->assertSame('Marque supprimée avec succès !', __('Marca excluída com sucesso!'));
    }

    /**
     * As mensagens tinham texto corrompido — bytes UTF-8 lidos como Latin-1.
     *
     * "Sem permissÃ£o para editar produtos" era o que o utilizador via. Se
     * tivesse sido embrulhado como estava, a chave do dicionário ficava com o
     * lixo lá dentro e a corrupção passava a ser permanente: traduzida, com
     * tradução aprovada, e errada para sempre.
     */
    public function test_nenhuma_mensagem_tem_texto_corrompido(): void
    {
        $ficheiros = array_merge(glob(app_path('Services/Invoicing/*.php')), glob(app_path('Http/Controllers/Api/Invoicing/*.php')));

        foreach ($ficheiros as $f) {
            $conteudo = file_get_contents($f);

            $this->assertDoesNotMatchRegularExpression(
                '/Ã[£§µ©¡º­³ª´‡ƒ‰•]|â€[""™"“–]|Â[§ºª´]/u',
                $conteudo,
                basename($f) . ' tem texto com a codificação partida.'
            );
        }
    }

    /**
     * NEM UM ACENTO PARTIDO NOS ECRÃS.
     *
     * Aqui vigiavam-se os Blades destes oito ecrãs, à procura de directivas
     * partidas pelo script que embrulhou 800 e tal cadeias — uma regex de
     * parênteses equilibrados só desce um nível, e havia `@foreach` com três.
     * Esses Blades foram-se com a migração para React e as cadeias mudaram-se
     * para os `.tsx`: é lá que o texto do utilizador vive agora, e é lá que um
     * acidente de codificação passa a viver também.
     *
     * A intenção é a mesma que a do ensaio das mensagens: "Sem permissÃ£o" é
     * o que o utilizador vê, e num ficheiro de ecrã ninguém repara nele — não
     * há tradução por aprovar que o denuncie.
     */
    public function test_nenhum_ecra_tem_texto_corrompido(): void
    {
        $ecras = array_merge(
            glob(resource_path('js/ecras/facturacao/*.tsx')),
            glob(resource_path('js/ecras/facturacao/*/*.tsx'))
        );

        $this->assertNotEmpty($ecras, 'o varrimento tem de encontrar os ecrãs da facturação');

        foreach ($ecras as $f) {
            $conteudo = file_get_contents($f);

            $this->assertDoesNotMatchRegularExpression(
                '/Ã[£§µ©¡º­³ª´‡ƒ‰•]|â€[""™"“–]|Â[§ºª´]/u',
                $conteudo,
                basename($f) . ' tem texto com a codificação partida.'
            );

            $this->assertTrue(
                (bool) preg_match('//u', $conteudo),
                basename($f) . ' não é UTF-8 válido.'
            );
        }
    }

    /**
     * Nenhuma chave do dicionário arrasta consigo a indentação do ficheiro.
     *
     * Uma chave que inclua os espaços de indentação passa a depender deles:
     * uma reformatação inocente do Blade parte a tradução e a linha volta a
     * português, sem erro nenhum a dizê-lo.
     *
     * A mudança de linha SOZINHA é legítima e não se proíbe — a mensagem de
     * "Adicionar ao ecrã principal" do iOS tem passos numerados e precisa
     * mesmo delas. O que denuncia o acidente é a mudança de linha seguida de
     * espaços: isso é o ficheiro a entrar na chave, não o autor a querê-lo.
     */
    public function test_nenhuma_chave_depende_da_indentacao(): void
    {
        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            foreach (array_keys($dicionario) as $chave) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\n[ \t]{2,}/',
                    $chave,
                    "A chave \"" . str_replace("\n", '⏎', mb_substr($chave, 0, 50))
                        . "…\" em {$lingua} traz a indentação do ficheiro."
                );
            }
        }
    }
}
