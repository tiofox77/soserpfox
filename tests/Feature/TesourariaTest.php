<?php

namespace Tests\Feature;

use App\Livewire\Treasury\Banks;
use App\Livewire\Treasury\CashRegisters;
use App\Livewire\Treasury\Reports;
use App\Livewire\Treasury\Transactions;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Bank;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\Account;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\TransactionType;
use App\Models\Treasury\TransactionCategory;
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
    public function test_tesouraria_traz_tipos_e_categorias_padrao_por_empresa(): void
    {
        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Treasury\TransactionClassifications::class, ['kind' => 'type']);

        $this->assertDatabaseHas('treasury_transaction_types', [
            'tenant_id' => $this->tenant->id, 'code' => 'INCOME', 'nature' => 'income',
        ]);
        $this->assertDatabaseHas('treasury_transaction_categories', [
            'tenant_id' => $this->tenant->id, 'code' => 'sale',
        ]);
    }

    public function test_utilizador_cria_tipo_e_categoria_e_usa_no_movimento(): void
    {
        $types = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Treasury\TransactionClassifications::class, ['kind' => 'type'])
            ->call('create')
            ->set('form.name', 'Financiamento recebido')
            ->set('form.code', 'FINANCIAMENTO')
            ->set('form.nature', 'income')
            ->call('save')->assertHasNoErrors();
        $type = TransactionType::where('tenant_id', $this->tenant->id)->where('code', 'FINANCIAMENTO')->firstOrFail();

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Treasury\TransactionClassifications::class, ['kind' => 'category'])
            ->call('create')
            ->set('form.name', 'Crédito bancário')
            ->set('form.code', 'bank_loan')
            ->set('form.transaction_type_id', $type->id)
            ->call('save')->assertHasNoErrors();
        $category = TransactionCategory::where('tenant_id', $this->tenant->id)->where('code', 'bank_loan')->firstOrFail();

        /*
         * E O MOVIMENTO NASCE COM OS DOIS. A natureza vem do TIPO e o código
         * textual acompanha o id da categoria — é por esse código que os
         * filtros antigos, o POS e os relatórios procuram.
         */
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $id = $this->postJson('/api/v1/invoicing/react/tesouraria/movimentos', [
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id,
            'amount' => 1000,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => PaymentMethod::where('tenant_id', $this->tenant->id)->firstOrFail()->id,
            'cash_register_id' => $this->caixaParaMovimentos()->id,
            'description' => 'Financiamento recebido',
            'status' => 'completed',
        ])->assertCreated()->json('id');

        $movimento = Transaction::findOrFail($id);

        $this->assertSame('income', $movimento->type);
        $this->assertSame('bank_loan', $movimento->category);
    }

    /** Um caixa para os movimentos caírem: o saldo tem de ter onde mexer. */
    private function caixaParaMovimentos(): CashRegister
    {
        return CashRegister::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Caixa dos ensaios',
            'code' => 'CXE-' . uniqid(),
            'opening_balance' => 0,
            'current_balance' => 0,
            'status' => 'open',
            'is_active' => true,
        ]);
    }

    public function test_tipo_e_categoria_de_outra_empresa_nao_sao_aceites(): void
    {
        $outra = \App\Models\Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);
        TransactionCategory::seedDefaultsForTenant($outra->id);
        $foreignType = TransactionType::withoutGlobalScopes()->where('tenant_id', $outra->id)->firstOrFail();

        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $this->postJson('/api/v1/invoicing/react/tesouraria/movimentos', [
            'transaction_type_id' => $foreignType->id,
            'amount' => 100,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => PaymentMethod::where('tenant_id', $this->tenant->id)->firstOrFail()->id,
            'cash_register_id' => $this->caixaParaMovimentos()->id,
            'description' => 'Não pode passar',
            'status' => 'completed',
        ])->assertStatus(422)->assertJsonValidationErrors('transaction_type_id');
    }

    public function test_abrir_e_fechar_turno_abre_e_fecha_o_caixa_atribuido(): void
    {
        $cash = CashRegister::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Caixa POS do operador',
            'code' => 'POS-' . uniqid(),
            'opening_balance' => 0,
            'current_balance' => 0,
            'expected_balance' => 0,
            'status' => 'closed',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/invoicing/react/turnos/abrir', [
                'opening_balance' => 500,
                'opening_notes' => 'Abertura conjunta',
            ])
            ->assertCreated();

        $cash->refresh();
        $this->assertSame('open', $cash->status);
        $this->assertEquals(500, (float) $cash->opening_balance);
        $this->assertEquals(500, (float) $cash->current_balance);

        $this->actingAs($this->user)
            ->postJson('/api/v1/invoicing/react/turnos/fechar', [
                'actual_cash' => 650,
                'closing_notes' => 'Fecho conjunto',
            ])
            ->assertOk();

        $cash->refresh();
        $this->assertSame('closed', $cash->status);
        $this->assertEquals(650, (float) $cash->current_balance);
        $this->assertNotNull($cash->closed_at);
        $this->assertDatabaseHas('invoicing_pos_shifts', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
        ]);
    }

    public function test_creditar_movimento_de_factura_abre_o_fluxo_fiscal_do_relatorio_pos(): void
    {
        $invoice = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'invoice_number' => 'FR ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 222,
            'tax_amount' => 0,
            'total' => 222,
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);
        $transaction = $this->transaccao([
            'invoice_id' => $invoice->id,
            'description' => 'Venda POS fiscal',
        ]);

        /*
         * O servidor RECUSA estornar aqui — e diz por onde se faz.
         *
         * Anular uma venda é emitir uma nota de crédito: linhas escolhidas,
         * imposto recalculado, stock reposto, hash SAFT e comunicação à AGT.
         * Um movimento de sinal contrário devolvia o dinheiro e deixava a
         * factura de pé, sem nada disso.
         */
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $this->postJson("/api/v1/invoicing/react/tesouraria/movimentos/{$transaction->id}/creditar")
            ->assertStatus(409)
            ->assertJsonPath('redireccionar', route('invoicing.pos.reports', [
                'credit_transaction' => $transaction->id,
            ]));
    }

    public function test_metodo_de_pagamento_novo_comeca_com_tipo_valido(): void
    {
        Livewire::actingAs($this->user)->test(\App\Livewire\Treasury\PaymentMethods::class)
            ->call('create')->assertSet('form.type', 'cash');
    }

    public function test_metodo_configurado_encaminha_o_recebimento_para_a_conta(): void
    {
        $account = Account::create([
            'tenant_id' => $this->tenant->id, 'account_name' => 'Conta Operacional',
            'bank_id' => Bank::create(['name' => 'Banco Operacional', 'code' => 'BOP-' . uniqid(), 'country' => 'AO'])->id,
            'account_number' => 'AO-' . uniqid(), 'currency' => 'AOA', 'account_type' => 'current',
            'initial_balance' => 100, 'current_balance' => 100, 'is_active' => true,
        ]);
        $method = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'TPA Teste', 'code' => 'TPA-' . uniqid(),
            'type' => 'card', 'is_active' => true, 'default_account_id' => $account->id,
        ]);
        $destination = app(\App\Services\Treasury\TreasuryMovementService::class)
            ->destination($method, $this->tenant->id);
        app(\App\Services\Treasury\TreasuryMovementService::class)->post([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'payment_method_id' => $method->id, 'account_id' => $destination['account_id'],
            'cash_register_id' => null, 'transaction_number' => 'TRX-' . uniqid(),
            'type' => 'income', 'category' => 'card', 'amount' => 2500, 'currency' => 'AOA',
            'transaction_date' => now(), 'description' => 'Recebimento', 'status' => 'completed',
        ]);

        $this->assertEquals(2600, (float) $account->fresh()->current_balance);
    }

    public function test_editar_e_apagar_movimento_recalcula_o_saldo(): void
    {
        $account = Account::create([
            'tenant_id' => $this->tenant->id, 'account_name' => 'Conta Movimento',
            'bank_id' => Bank::create(['name' => 'Banco Movimento', 'code' => 'BMV-' . uniqid(), 'country' => 'AO'])->id,
            'account_number' => 'MOV-' . uniqid(), 'currency' => 'AOA', 'account_type' => 'current',
            'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true,
        ]);
        $method = PaymentMethod::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create',
            'treasury.transactions.edit', 'treasury.transactions.delete');

        TransactionCategory::seedDefaultsForTenant((int) $this->tenant->id);
        $tipo = TransactionType::where('tenant_id', $this->tenant->id)->where('nature', 'income')->firstOrFail();

        $corpo = [
            'transaction_type_id' => $tipo->id,
            'amount' => 1000,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => $method->id,
            'account_id' => $account->id,
            'description' => 'Entrada teste',
            'status' => 'completed',
        ];

        $raiz = '/api/v1/invoicing/react/tesouraria/movimentos';

        $id = $this->postJson($raiz, $corpo)->assertCreated()->json('id');
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);

        $this->putJson("{$raiz}/{$id}", ['amount' => 600] + $corpo)->assertOk();
        $this->assertEquals(600, (float) $account->fresh()->current_balance);

        $this->deleteJson("{$raiz}/{$id}")->assertOk();
        $this->assertEquals(0, (float) $account->fresh()->current_balance);
    }

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

        $this->comPermissoes('treasury.transactions.view');

        $categorias = collect(
            $this->getJson('/api/v1/invoicing/react/tesouraria/movimentos/opcoes')
                ->assertOk()->json('categorias_para_filtrar')
        );

        $this->assertSame('Pagamento digital', $categorias->firstWhere('valor', 'digital_payment')['rotulo'] ?? null);
    }

    public function test_uma_categoria_desconhecida_e_legivel_e_nao_fica_em_branco(): void
    {
        $this->assertSame('Numerário', CategoriasDeTesouraria::nome('cash'));
        $this->assertSame('Sem categoria', CategoriasDeTesouraria::nome(null));
        $this->assertSame('Coisa estranha', CategoriasDeTesouraria::nome('coisa_estranha'));
    }

    public function test_filtra_por_data(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->transaccao(['transaction_date' => now()->subMonths(2)]);
        $recente = $this->transaccao(['transaction_date' => now()]);

        $r = $this->getJson($this->morada(['de' => now()->subDays(7)->toDateString()]))->assertOk();

        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame($recente->id, $r->json('data.0.id'));
    }

    public function test_filtra_por_categoria(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->transaccao(['category' => 'cash']);
        $this->transaccao(['category' => 'card']);

        $this->assertSame(1, $this->getJson($this->morada(['categoria' => 'card']))->assertOk()->json('meta.total'));
    }

    /**
     * Os totais do topo seguem os filtros.
     *
     * Somavam sempre tudo desde sempre: filtrar por um mês e ver o total de
     * três anos faz desconfiar dos dois números, e com razão.
     */
    public function test_os_totais_respeitam_os_filtros(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->transaccao(['amount' => 5000, 'transaction_date' => now()->subMonths(2)]);
        $this->transaccao(['amount' => 1000, 'transaction_date' => now()]);

        $r = $this->getJson($this->morada(['de' => now()->subDays(7)->toDateString()]))->assertOk();

        $this->assertEquals(1000, (float) $r->json('resumo.entradas'));
    }

    public function test_limpar_filtros_devolve_tudo(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->transaccao(['transaction_date' => now()->subMonths(2)]);
        $this->transaccao(['transaction_date' => now()]);

        // Limpar filtros é o ecrã voltar a pedir sem nenhum — e a lista
        // inteira volta.
        $this->assertSame(1, $this->getJson($this->morada(['de' => now()->subDays(7)->toDateString()]))->json('meta.total'));
        $this->assertSame(2, $this->getJson($this->morada())->assertOk()->json('meta.total'));
    }

    /** A morada da lista com os filtros que se quiser. */
    private function morada(array $filtros = []): string
    {
        $raiz = '/api/v1/invoicing/react/tesouraria/movimentos';

        return $filtros ? $raiz . '?' . http_build_query($filtros) : $raiz;
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
