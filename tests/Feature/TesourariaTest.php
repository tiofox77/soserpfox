<?php

namespace Tests\Feature;

use App\Livewire\Treasury\Banks;
use App\Livewire\Treasury\CashRegisters;
use App\Livewire\Treasury\Reports;
use App\Livewire\Treasury\Transactions;
use App\Models\Treasury\Bank;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use App\Models\User;
use App\Support\CategoriasDeTesouraria;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A Tesouraria.
 *
 * Quatro coisas estavam partidas, e as três primeiras pela mesma razão de
 * fundo: o ecrã fazia o trabalho e não dizia nada a ninguém.
 *
 *   1. Criar um banco funcionava e parecia não funcionar — a confirmação ia
 *      por `session()->flash('message')`, que NADA nesta aplicação renderiza.
 *   2. O responsável de um caixa saía de `users.tenant_id`, que não é a fonte
 *      de verdade: quem entra na empresa entra pelo pivô.
 *   3. As categorias das transacções eram seis opções escritas à mão que não
 *      existiam na base, e não havia filtro por data, conta ou caixa.
 *   4. Os relatórios financeiros não se podiam descarregar.
 */
class TesourariaTest extends TenantTestCase
{
    // ══════════════ 1. o ecrã tem de falar ══════════════

    /**
     * A confirmação sai por evento, não por flash.
     *
     * O layout ouve `Livewire.on('success')` e mostra um aviso. Um
     * `session()->flash('message')` não é renderizado em lado nenhum: o banco
     * era criado e o utilizador não via nada — que se lê como "não funciona".
     */
    public function test_criar_banco_avisa_o_utilizador(): void
    {
        Livewire::actingAs($this->user)
            ->test(Banks::class)
            ->call('create')
            ->set('form.name', $nome = 'BFA Teste ' . uniqid())
            ->set('form.code', 'BFA-' . uniqid())
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('success');

        $this->assertSame(1, Bank::where('name', $nome)->count());
    }

    public function test_eliminar_banco_avisa_o_utilizador(): void
    {
        $banco = Bank::create(['name' => 'Banco X', 'code' => 'BX-' . uniqid(), 'country' => 'AO']);

        Livewire::actingAs($this->user)
            ->test(Banks::class)
            ->call('confirmDelete', $banco->id)
            ->call('deleteBank')
            ->assertDispatched('success');

        $this->assertNull(Bank::find($banco->id));
    }

    public function test_nenhum_ecra_da_tesouraria_usa_flash_invisivel(): void
    {
        // Guarda contra a reincidência: `session()->flash('message')` não é
        // renderizado em lado nenhum desta aplicação.
        foreach (glob(app_path('Livewire/Treasury/*.php')) as $ficheiro) {
            $this->assertStringNotContainsString(
                "session()->flash('message'",
                file_get_contents($ficheiro),
                basename($ficheiro) . ' voltou a usar flash invisível'
            );
        }
    }

    // ══════════════ 2. o responsável do caixa ══════════════

    /**
     * O responsável vem do pivô, não da coluna antiga.
     *
     * Medido em produção: uma empresa com 9 pessoas no pivô mostrava 8 — e a
     * que faltava não podia ser escolhida como responsável de caixa nenhum.
     */
    public function test_o_responsavel_sai_do_pivot_e_nao_da_coluna_antiga(): void
    {
        $novo = User::create([
            'name' => 'Ana Responsável',
            'email' => 'ana-' . uniqid() . '@empresa.ao',
            'password' => bcrypt('x'),
            'is_active' => true,
            // De propósito SEM tenant_id: entra só pelo pivô, como acontece a
            // quem é acrescentado pela gestão de utilizadores.
        ]);

        $this->tenant->users()->attach($novo->id, ['is_active' => true]);

        $lista = Livewire::actingAs($this->user)
            ->test(CashRegisters::class)
            ->viewData('users');

        $this->assertTrue(
            $lista->contains('id', $novo->id),
            'quem entra pelo pivô tem de poder ser responsável de caixa'
        );
    }

    public function test_nao_aceita_responsavel_de_outra_empresa(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra Empresa', 'slug' => 'outra-' . uniqid(), 'is_active' => true,
        ]);

        $alheio = User::create([
            'name' => 'Alheio', 'email' => 'alheio-' . uniqid() . '@x.ao',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $outra->users()->attach($alheio->id, ['is_active' => true]);

        Livewire::actingAs($this->user)
            ->test(CashRegisters::class)
            ->call('create')
            ->set('form.name', 'Caixa 1')
            ->set('form.code', 'CX-' . uniqid())
            ->set('form.user_id', $alheio->id)
            ->call('save')
            ->assertHasErrors('form.user_id');

        $this->assertSame(0, CashRegister::where('user_id', $alheio->id)->count());
    }

    public function test_cria_um_caixa_com_responsavel_da_casa(): void
    {
        Livewire::actingAs($this->user)
            ->test(CashRegisters::class)
            ->call('create')
            ->set('form.name', 'Caixa Balcão')
            ->set('form.code', 'CX-' . uniqid())
            ->set('form.user_id', $this->user->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('success');

        $this->assertSame(1, CashRegister::where('name', 'Caixa Balcão')->count());
    }

    // ══════════════ 3. transacções ══════════════

    private function transaccao(array $extra = []): Transaction
    {
        return Transaction::create(array_merge([
            'tenant_id'          => $this->tenant->id,
            'user_id'            => $this->user->id,
            'transaction_number' => 'TRX-' . strtoupper(uniqid()),
            'type'               => 'income',
            'category'           => 'cash',
            'amount'             => 1000,
            'currency'           => 'AOA',
            'transaction_date'   => now(),
            'status'             => 'completed',
        ], $extra));
    }

    /**
     * A lista de categorias inclui o que a empresa REALMENTE tem.
     *
     * O POS grava o tipo do método de pagamento na coluna `category`. Se o
     * filtro só oferecesse as escritas à mão, a maior parte do movimento de
     * uma empresa ficava impossível de filtrar.
     */
    public function test_o_filtro_de_categorias_inclui_as_que_existem_na_base(): void
    {
        $this->transaccao(['category' => 'digital_payment']);

        $categorias = Livewire::actingAs($this->user)
            ->test(Transactions::class)
            ->viewData('categoriasParaFiltrar');

        $this->assertArrayHasKey('digital_payment', $categorias);
        $this->assertSame('Pagamento digital', $categorias['digital_payment']);
    }

    public function test_uma_categoria_desconhecida_e_legivel_e_nao_fica_em_branco(): void
    {
        $this->assertSame('Numerário', CategoriasDeTesouraria::nome('cash'));
        $this->assertSame('Sem categoria', CategoriasDeTesouraria::nome(null));
        $this->assertSame('Coisa estranha', CategoriasDeTesouraria::nome('coisa_estranha'));
    }

    public function test_filtra_por_data(): void
    {
        $this->transaccao(['transaction_date' => now()->subMonths(2)]);
        $recente = $this->transaccao(['transaction_date' => now()]);

        $c = Livewire::actingAs($this->user)
            ->test(Transactions::class)
            ->set('dataDe', now()->subDays(7)->toDateString());

        $this->assertSame(1, $c->viewData('transactions')->total());
        $this->assertSame($recente->id, $c->viewData('transactions')->first()->id);
    }

    public function test_filtra_por_categoria(): void
    {
        $this->transaccao(['category' => 'cash']);
        $this->transaccao(['category' => 'card']);

        $c = Livewire::actingAs($this->user)
            ->test(Transactions::class)
            ->set('filterCategory', 'card');

        $this->assertSame(1, $c->viewData('transactions')->total());
    }

    /**
     * Os totais do topo seguem os filtros.
     *
     * Somavam sempre tudo desde sempre: filtrar por um mês e ver o total de
     * três anos faz desconfiar dos dois números, e com razão.
     */
    public function test_os_totais_respeitam_os_filtros(): void
    {
        $this->transaccao(['amount' => 5000, 'transaction_date' => now()->subMonths(2)]);
        $this->transaccao(['amount' => 1000, 'transaction_date' => now()]);

        $c = Livewire::actingAs($this->user)
            ->test(Transactions::class)
            ->set('dataDe', now()->subDays(7)->toDateString());

        $this->assertEquals(1000, (float) $c->viewData('totalIncome'));
    }

    public function test_limpar_filtros_devolve_tudo(): void
    {
        $this->transaccao(['transaction_date' => now()->subMonths(2)]);
        $this->transaccao(['transaction_date' => now()]);

        $c = Livewire::actingAs($this->user)
            ->test(Transactions::class)
            ->set('dataDe', now()->subDays(7)->toDateString())
            ->call('limparFiltros');

        $this->assertSame(2, $c->viewData('transactions')->total());
    }

    // ══════════════ 4. exportar relatórios ══════════════

    public function test_o_ecra_de_relatorios_da_os_parametros_de_descarga(): void
    {
        $c = Livewire::actingAs($this->user)
            ->test(Reports::class)
            ->set('reportType', 'dre');

        $p = $c->instance()->parametrosDeExportacao;

        $this->assertSame('dre', $p['tipo']);
        $this->assertNotEmpty($p['de']);
        $this->assertNotEmpty($p['ate']);
    }

    public function test_descarrega_o_pdf_de_cada_relatorio(): void
    {
        $this->comModulo('treasury');
        $this->transaccao();
        $this->actingAs($this->user);

        foreach (array_keys(\App\Services\Treasury\RelatoriosDeTesouraria::TIPOS) as $tipo) {
            $r = $this->get(route('treasury.reports.pdf', ['tipo' => $tipo]));

            $r->assertOk();
            $this->assertSame('application/pdf', $r->headers->get('content-type'), "PDF de {$tipo}");
        }
    }

    public function test_descarrega_o_excel_de_cada_relatorio(): void
    {
        $this->comModulo('treasury');
        $this->transaccao();
        $this->actingAs($this->user);

        foreach (array_keys(\App\Services\Treasury\RelatoriosDeTesouraria::TIPOS) as $tipo) {
            $r = $this->get(route('treasury.reports.excel', ['tipo' => $tipo]));

            $r->assertOk();
            $this->assertStringContainsString('spreadsheetml', $r->headers->get('content-type'), "Excel de {$tipo}");
        }
    }

    /** Um tipo inventado no URL alimenta um match — não pode passar. */
    public function test_um_relatorio_desconhecido_e_recusado(): void
    {
        $this->comModulo('treasury');

        $this->actingAs($this->user)
            ->get(route('treasury.reports.pdf', ['tipo' => 'inventado']))
            ->assertNotFound();
    }

    public function test_datas_trocadas_nao_dao_relatorio_vazio(): void
    {
        $this->comModulo('treasury');
        $this->transaccao(['transaction_date' => now()->subDays(3)]);

        $this->actingAs($this->user)
            ->get(route('treasury.reports.pdf', [
                'tipo' => 'cash_flow',
                'de'   => now()->toDateString(),
                'ate'  => now()->subDays(10)->toDateString(),
            ]))
            ->assertOk();
    }

    /**
     * O ecrã e o ficheiro têm de dar a mesma conta.
     *
     * É por isso que as consultas vivem no serviço e não no componente.
     */
    public function test_o_ecra_e_o_servico_dao_o_mesmo_numero(): void
    {
        $this->transaccao(['amount' => 2500, 'type' => 'income']);

        $doEcra = Livewire::actingAs($this->user)
            ->test(Reports::class)
            ->set('period', 'year')
            ->viewData('totalIncome');

        $doServico = (new \App\Services\Treasury\RelatoriosDeTesouraria(
            $this->tenant->id,
            now()->startOfYear()->format('Y-m-d'),
            now()->endOfYear()->format('Y-m-d')
        ))->fluxoDeCaixa()['totalIncome'];

        $this->assertEquals($doServico, $doEcra);
    }
}
