<?php

namespace Tests\Feature;

use App\Http\Middleware\DefinirLingua;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O circuito multi-língua — fase 0 do plano (docs/PLANO-MULTILINGUA.md).
 *
 * Duas metades: o DETECTOR, que varre o código à procura de __('...') e
 * rebenta com a lista do que está por traduzir em en/fr — é ele que
 * transforma "acho que está traduzido" em "está traduzido" —, e o CIRCUITO,
 * que prova que a língua escolhida chega mesmo ao ecrã.
 */
class TraducoesTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O ecrã piloto vive atrás do tenant.module:invoicing — sem o módulo
        // activo, tudo dava 403 e o teste media a porta, não a língua.
        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    /** Onde o detector procura. Alarga-se lote a lote, com a fase 1. */
    private const ONDE = [
        'resources/views/livewire/invoicing/warehouse-transfer',
        'app/Livewire/Invoicing/WarehouseTransfer.php',
        'resources/views/layouts/app.blade.php',
    ];

    // ==================== o detector ====================

    public function test_todas_as_cadeias_usadas_tem_traducao_em_en_e_fr(): void
    {
        $usadas = $this->cadeiasUsadas();

        $this->assertNotEmpty($usadas, 'O varrimento tem de encontrar alguma coisa — o piloto usa __().');

        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            $this->assertIsArray($dicionario, "lang/{$lingua}.json tem de ser JSON válido.");

            $emFalta = array_diff($usadas, array_keys($dicionario));

            $this->assertEmpty(
                $emFalta,
                "Cadeias sem tradução em {$lingua}:\n  " . implode("\n  ", $emFalta)
            );
        }
    }

    /** Uma tradução vazia é uma falta disfarçada. */
    public function test_nenhuma_traducao_esta_vazia(): void
    {
        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            foreach ($dicionario as $chave => $valor) {
                $this->assertNotSame('', trim((string) $valor), "Tradução vazia em {$lingua}: {$chave}");
            }
        }
    }

    /**
     * Os placeholders têm de sobreviver à tradução.
     *
     * Uma tradução que perca o :ref ou o :n mostra a frase com um buraco —
     * ou pior, com o placeholder por preencher.
     */
    public function test_os_placeholders_sobrevivem_a_traducao(): void
    {
        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            foreach ($dicionario as $chave => $valor) {
                preg_match_all('/:(\w+)/', $chave, $originais);

                foreach ($originais[1] as $p) {
                    $this->assertStringContainsString(
                        ":{$p}",
                        $valor,
                        "A tradução {$lingua} de \"{$chave}\" perdeu o placeholder :{$p}."
                    );
                }
            }
        }
    }

    // ==================== o circuito ====================

    public function test_o_utilizador_com_locale_en_ve_o_piloto_em_ingles(): void
    {
        $this->user->update(['locale' => 'en']);

        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee('Stock Transfers and Adjustments')
            ->assertSee('Movement History')
            ->assertDontSee('Histórico de Movimentações');
    }

    public function test_o_utilizador_com_locale_fr_ve_o_piloto_em_frances(): void
    {
        $this->user->update(['locale' => 'fr']);

        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee('Transferts et ajustements de stock')
            ->assertDontSee('Histórico de Movimentações');
    }

    public function test_sem_escolha_o_ecra_fala_portugues(): void
    {
        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee('Histórico de Movimentações');
    }

    /** ?lang= muda a língua e guarda-a no perfil. */
    public function test_o_parametro_lang_muda_e_fica_guardado(): void
    {
        $this->get('/invoicing/warehouse-transfer?lang=en')
            ->assertOk()
            ->assertSee('Movement History');

        $this->assertSame('en', $this->user->refresh()->locale);

        // E a visita seguinte, sem parâmetro, continua em inglês.
        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee('Movement History');
    }

    /** Uma língua inventada não passa. */
    public function test_uma_lingua_invalida_e_ignorada(): void
    {
        $this->get('/invoicing/warehouse-transfer?lang=xx')->assertOk();

        $this->assertNull($this->user->refresh()->locale);
    }

    /** A língua da empresa vale para quem não escolheu a sua. */
    public function test_a_lingua_da_empresa_e_o_ponto_de_partida(): void
    {
        $this->tenant->update(['locale' => 'fr']);

        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee('Transferts et ajustements de stock');
    }

    /** As mensagens do componente saem na língua do utilizador. */
    public function test_as_mensagens_do_componente_saem_traduzidas(): void
    {
        $this->user->update(['locale' => 'en']);
        app()->setLocale('en');

        Livewire::test(\App\Livewire\Invoicing\WarehouseTransfer::class)
            ->call('addProductToTransfer')
            ->assertDispatched('error', message: 'Select the source warehouse first.');
    }

    // ==================== o varrimento ====================

    /** Todas as cadeias dentro de __('...') e trans_choice('...') nos sítios vigiados. */
    private function cadeiasUsadas(): array
    {
        $cadeias = [];

        foreach (self::ONDE as $caminho) {
            $absoluto = base_path($caminho);
            $ficheiros = is_dir($absoluto)
                ? array_filter(
                    iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluto))),
                    fn ($f) => $f->isFile() && str_ends_with($f->getFilename(), '.php')
                )
                : (is_file($absoluto) ? [new \SplFileInfo($absoluto)] : []);

            foreach ($ficheiros as $f) {
                $conteudo = file_get_contents($f->getPathname());

                // __('...') e trans_choice('...') com aspas simples; as
                // plicas escapadas dentro da cadeia ficam de fora do lote
                // por agora — o detector prefere acusar de menos a rebentar
                // com falsos positivos.
                if (preg_match_all("/(?:__|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)+)'/", $conteudo, $m)) {
                    foreach ($m[1] as $cadeia) {
                        $cadeias[stripslashes($cadeia)] = true;
                    }
                }
            }
        }

        return array_keys($cadeias);
    }
}
