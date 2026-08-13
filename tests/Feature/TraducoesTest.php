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

    /**
     * Onde o detector procura. Alarga-se lote a lote, com a fase 1.
     *
     * Acrescentar um caminho aqui é o que fecha um lote: a partir desse
     * momento, qualquer cadeia usada sem tradução põe a suite vermelha.
     */
    private const ONDE = [
        // Fase 0 — piloto
        'resources/views/livewire/invoicing/warehouse-transfer',
        'app/Livewire/Invoicing/WarehouseTransfer.php',
        'resources/views/layouts/app.blade.php',

        // Fase 1, lote 1 — vendas
        'resources/views/livewire/invoicing/faturas-venda',
        'resources/views/livewire/invoicing/proformas-venda',
        'resources/views/livewire/invoicing/receipts',
        'resources/views/livewire/invoicing/credit-notes',
        'resources/views/livewire/invoicing/debit-notes',
        'app/Livewire/Invoicing/Sales',
        'app/Livewire/Invoicing/Receipts',
        'app/Livewire/Invoicing/CreditNotes',
        'app/Livewire/Invoicing/DebitNotes',

        // Fase 1, lote 2 — POS (inclui o JavaScript do PWA offline)
        'resources/views/invoicing/offline/pos.blade.php',
        'resources/views/livewire/pos',
        'resources/views/livewire/invoicing/pos',
        'app/Livewire/POS',
        'app/Livewire/Invoicing/POS',
        'public/js/pwa-invoicing.js',
        'public/js/pos-offline-ticket.js',

        // Fase 1, lote 3 — o menu lateral e o painel.
        //
        // Foram trazidos para a frente do plano (estavam no lote 5) por uma
        // razão que só se vê com o sistema à frente: são a primeira coisa que
        // aparece. Quem escolhe inglês e cai num menu inteiro em português
        // conclui que a tradução não funciona, mesmo com 700 cadeias já
        // traduzidas por trás. O app.blade.php já cá estava desde a fase 0,
        // mas na altura ainda não tinha um único __().
        'resources/views/livewire/invoicing/invoicing-dashboard.blade.php',
        'app/Livewire/Invoicing/InvoicingDashboard.php',
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

    /**
     * Todas as cadeias dentro de __(), __n() e trans_choice() nos sítios
     * vigiados — em PHP, Blade E JavaScript.
     *
     * O .js entrou com o lote 2: o POS tem cadeias em JavaScript, e um
     * detector que só olhasse para .php deixava passar exactamente a parte
     * do sistema onde uma falta é mais cara — o caixa, offline, sem forma de
     * ser avisado.
     */
    private function cadeiasUsadas(): array
    {
        $cadeias = [];

        foreach (self::ONDE as $caminho) {
            $absoluto = base_path($caminho);
            $ficheiros = is_dir($absoluto)
                ? array_filter(
                    iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluto))),
                    fn ($f) => $f->isFile()
                        && (str_ends_with($f->getFilename(), '.php') || str_ends_with($f->getFilename(), '.js'))
                )
                : (is_file($absoluto) ? [new \SplFileInfo($absoluto)] : []);

            foreach ($ficheiros as $f) {
                $conteudo = file_get_contents($f->getPathname());

                // __('...'), __n('...') e trans_choice('...'), com aspas
                // simples. As plicas escapadas dentro da cadeia ficam de fora
                // por agora — o detector prefere acusar de menos a rebentar
                // com falsos positivos.
                if (preg_match_all("/(?:__n|__|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)+)'/", $conteudo, $m)) {
                    foreach ($m[1] as $cadeia) {
                        $cadeias[self::interpretarEscapes($cadeia)] = true;
                    }
                }

                // Em JavaScript também se escreve com aspas duplas.
                if (str_ends_with($f->getFilename(), '.js')
                    && preg_match_all('/(?:__n|__)\(\s*"((?:[^"\\\\]|\\\\.)+)"/', $conteudo, $m)) {
                    foreach ($m[1] as $cadeia) {
                        $cadeias[self::interpretarEscapes($cadeia)] = true;
                    }
                }
            }
        }

        return array_keys($cadeias);
    }

    /**
     * O texto que a linguagem vê em execução, e não o que está escrito no
     * ficheiro.
     *
     * Isto era um `stripslashes()`, e estava errado de uma forma calada:
     * stripslashes tira a barra e deixa a letra, portanto `\n` no código
     * virava a letra "n". A chave que o detector procurava era uma terceira
     * coisa — nem o que está no ficheiro, nem o que o JavaScript procura em
     * execução, que é uma mudança de linha a sério.
     *
     * Resultado prático: a mensagem de "Adicionar ao ecrã principal" do iOS
     * nunca teria sido traduzida, e ninguém saberia porquê.
     */
    private static function interpretarEscapes(string $cadeia): string
    {
        return strtr($cadeia, [
            '\\n'  => "\n",
            '\\r'  => "\r",
            '\\t'  => "\t",
            "\\'"  => "'",
            '\\"'  => '"',
            '\\\\' => '\\',
        ]);
    }
}
