<?php

namespace Tests\Feature;

use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\TransactionType;
use Tests\TenantTestCase;

/**
 * OS MOVIMENTOS DA TESOURARIA EM REACT — a API.
 *
 * O que este ensaio guarda, por ordem de importância:
 *
 * 1. **O DINHEIRO MEXE-SE NUMA PORTA SÓ.** Gravar um movimento move o saldo
 *    da conta ou do caixa pelo `TreasuryMovementService`. O ecrã em Livewire
 *    chamava-o para gravar mas tinha ao lado um `updateBalance()` próprio,
 *    com `increment`/`decrement` sem bloqueio — duas maneiras de mexer no
 *    mesmo saldo acabam por divergir numa delas.
 *
 * 2. **UM DESTINO, E SÓ UM.** Conta OU caixa. Com os dois, o serviço escolhia
 *    a conta e ignorava o caixa em silêncio; com nenhum, o movimento ficava a
 *    pairar sem mexer em saldo nenhum.
 *
 * 3. **CORRIGIR NÃO DUPLICA NEM PERDE.** Editar um movimento concluído desfaz
 *    o saldo com os valores ANTIGOS e refá-lo com os novos. Mudar a conta de
 *    destino sem isso deixava a antiga inflada para sempre.
 *
 * 4. **ESTORNAR UMA VENDA NÃO SE FAZ AQUI.** Com factura por trás, anular é
 *    emitir uma nota de crédito — com linhas, imposto, stock, hash e AGT. O
 *    servidor recusa e diz por onde.
 *
 * 5. **AS PERMISSÕES EXISTEM.** As quatro `treasury.transactions.*` estavam
 *    na base e nenhuma rota as aplicava.
 */
class ApiDosMovimentosDaTesourariaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/tesouraria/movimentos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('treasury');
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function conta(array $por = []): Account
    {
        // Uma conta bancária pertence a um banco — a coluna é obrigatória.
        $banco = \App\Models\Treasury\Bank::firstOrCreate(
            ['code' => 'BFA'],
            ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]
        );

        return Account::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'bank_id' => $banco->id,
            'account_name' => 'Conta ' . uniqid(),
            'account_number' => (string) random_int(100000, 999999),
            'currency' => 'AOA',
            'initial_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ], $por));
    }

    private function caixa(array $por = []): CashRegister
    {
        return CashRegister::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Caixa ' . uniqid(),
            'code' => 'CX' . random_int(1000, 9999),
            'opening_balance' => 0,
            'current_balance' => 0,
            'status' => 'open',
            'is_active' => true,
        ], $por));
    }

    private function forma(array $por = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Transferência ' . uniqid(),
            'code' => 'TRF' . random_int(100, 999),
            'type' => 'transfer',
            'is_active' => true,
        ], $por));
    }

    private function tipo(string $natureza = 'income'): TransactionType
    {
        return TransactionType::create([
            'tenant_id' => $this->tenant->id,
            'name' => ucfirst($natureza) . ' ' . uniqid(),
            'code' => strtoupper(substr($natureza, 0, 3)) . random_int(100, 999),
            'nature' => $natureza,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function corpo(array $por = []): array
    {
        return array_merge([
            'transaction_type_id' => $this->tipo()->id,
            'transaction_category_id' => null,
            'amount' => 5000,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => $this->forma()->id,
            'account_id' => null,
            'cash_register_id' => null,
            'reference' => 'REF-1',
            'description' => 'Venda de balcão',
            'notes' => null,
            'status' => 'completed',
        ], $por);
    }

    /* ─── As permissões ───────────────────────────────────────────────── */

    /** @test */
    public function sem_permissao_de_ver_a_lista_esta_fechada(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
    }

    /**
     * VER NÃO É LANÇAR.
     *
     * Quem só consulta o extracto não lança, não corrige e não apaga
     * dinheiro. A morada de sempre não exigia permissão nenhuma.
     *
     * @test
     */
    public function ver_nao_da_direito_a_lancar_corrigir_nem_apagar(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $conta = $this->conta();

        $this->postJson(self::RAIZ, $this->corpo(['account_id' => $conta->id]))->assertForbidden();

        $m = Transaction::create($this->corpo(['account_id' => $conta->id]) + [
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'income',
            'transaction_number' => 'TRX-TESTE-1',
        ]);

        $this->putJson(self::RAIZ . '/' . $m->id, $this->corpo(['account_id' => $conta->id]))->assertForbidden();
        $this->deleteJson(self::RAIZ . '/' . $m->id)->assertForbidden();
        $this->postJson(self::RAIZ . '/' . $m->id . '/creditar')->assertForbidden();
    }

    /** @test */
    public function as_opcoes_trazem_o_que_o_formulario_precisa(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta();
        $caixa = $this->caixa();
        // A empresa já nasce com formas de pagamento de omissão: esta é a
        // NOSSA, e é por id que se encontra.
        $minha = $this->forma(['type' => 'cash', 'default_cash_register_id' => $caixa->id]);
        $this->tipo('expense');

        $r = $this->getJson(self::RAIZ . '/opcoes')->assertOk();

        $this->assertNotEmpty($r->json('contas'));
        $this->assertNotEmpty($r->json('caixas'));
        $this->assertNotEmpty($r->json('tipos'));
        $this->assertTrue($r->json('permissoes.pode_criar'));
        $this->assertFalse($r->json('permissoes.pode_apagar'));

        // O DESTINO QUE O MÉTODO JÁ SABE viaja com ele: é o que o ecrã
        // preenche sozinho ao escolher a forma de pagamento.
        $dinheiro = collect($r->json('formas_de_pagamento'))->firstWhere('id', $minha->id);
        $this->assertSame($caixa->id, $dinheiro['caixa_padrao']);
        $this->assertSame('cash', $dinheiro['tipo']);

        $this->assertContains($conta->id, collect($r->json('contas'))->pluck('id')->all());
    }

    /* ─── Lançar ──────────────────────────────────────────────────────── */

    /**
     * GRAVAR MOVE O SALDO — e o número sai da sequência do ano.
     *
     * @test
     */
    public function lancar_uma_entrada_move_o_saldo_da_conta(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta(['current_balance' => 1000]);

        $r = $this->postJson(self::RAIZ, $this->corpo(['account_id' => $conta->id, 'amount' => 5000]))
            ->assertCreated();

        $this->assertStringStartsWith('TRX-' . date('Y') . '-', (string) $r->json('numero'));
        $this->assertEqualsWithDelta(6000, $conta->fresh()->current_balance, 0.01);
    }

    /** Uma saída tira, e do caixa quando é no caixa que cai. @test */
    public function lancar_uma_saida_tira_do_caixa(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $caixa = $this->caixa(['current_balance' => 8000]);

        $this->postJson(self::RAIZ, $this->corpo([
            'transaction_type_id' => $this->tipo('expense')->id,
            'cash_register_id' => $caixa->id,
            'amount' => 3000,
        ]))->assertCreated();

        $this->assertEqualsWithDelta(5000, $caixa->fresh()->current_balance, 0.01);
    }

    /**
     * A NATUREZA VEM DO TIPO, e não do que o ecrã disser.
     *
     * É o tipo de movimento que declara se é entrada ou saída, e é isso que
     * decide o sinal no saldo. Um pedido forjado a dizer o contrário não
     * transforma uma saída em entrada.
     *
     * @test
     */
    public function a_natureza_vem_do_tipo_e_nao_do_pedido(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta(['current_balance' => 10000]);

        $r = $this->postJson(self::RAIZ, $this->corpo([
            'transaction_type_id' => $this->tipo('expense')->id,
            'account_id' => $conta->id,
            'amount' => 2000,
            // O ecrã mente: diz que isto é uma entrada.
            'type' => 'income',
        ]))->assertCreated();

        $this->assertSame('expense', Transaction::findOrFail($r->json('id'))->type);
        $this->assertEqualsWithDelta(8000, $conta->fresh()->current_balance, 0.01);
    }

    /**
     * O CÓDIGO DA CATEGORIA ACOMPANHA O ID.
     *
     * A coluna `category` é texto e é por ela que os filtros antigos, o POS e
     * os relatórios procuram. Gravar só o id deixava-os todos sem ver o
     * movimento.
     *
     * @test
     */
    public function a_categoria_grava_o_codigo_ao_lado_do_id(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $tipo = $this->tipo();

        $categoria = \App\Models\Treasury\TransactionCategory::create([
            'tenant_id' => $this->tenant->id,
            'transaction_type_id' => $tipo->id,
            'name' => 'Empréstimo bancário',
            'code' => 'bank_loan',
            'is_active' => true,
        ]);

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'transaction_type_id' => $tipo->id,
            'transaction_category_id' => $categoria->id,
            'account_id' => $this->conta()->id,
        ]))->assertCreated()->json('id');

        $this->assertSame('bank_loan', Transaction::findOrFail($id)->category);
    }

    /**
     * O TIPO DA EMPRESA DO LADO NÃO SERVE.
     *
     * As regras de validação prendem-no à empresa activa: um id apanhado de
     * outra classificaria o dinheiro desta por uma tabela que não é a dela.
     *
     * @test
     */
    public function um_tipo_de_outra_empresa_nao_e_aceite(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $outra = \App\Models\Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);

        $alheio = TransactionType::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio', 'code' => 'ALH' . random_int(100, 999),
            'nature' => 'income', 'is_active' => true,
        ]);

        $this->postJson(self::RAIZ, $this->corpo([
            'transaction_type_id' => $alheio->id,
            'account_id' => $this->conta()->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('transaction_type_id');
    }

    /**
     * O FILTRO CONHECE AS CATEGORIAS QUE ESTÃO NOS MOVIMENTOS.
     *
     * O POS e o restaurante escrevem categorias que não estão na tabela do
     * catálogo. Sem as juntar, uma linha ficava invisível ao seu próprio
     * filtro.
     *
     * @test
     */
    public function o_filtro_conhece_as_categorias_que_o_pos_escreve(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        Transaction::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'transaction_number' => 'TRX-CAT-1',
            'type' => 'income', 'amount' => 500, 'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'description' => 'Do balcão', 'status' => 'completed',
            'category' => 'digital_payment',
        ]);

        $filtros = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('categorias_para_filtrar'));

        $this->assertSame('Pagamento digital', $filtros->firstWhere('valor', 'digital_payment')['rotulo'] ?? null);
    }

    /** Um movimento pendente não mexe no saldo. @test */
    public function o_que_esta_pendente_nao_mexe_no_saldo(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta(['current_balance' => 1000]);

        $this->postJson(self::RAIZ, $this->corpo(['account_id' => $conta->id, 'status' => 'pending']))
            ->assertCreated();

        $this->assertEqualsWithDelta(1000, $conta->fresh()->current_balance, 0.01);
    }

    /**
     * UM DESTINO, E SÓ UM.
     *
     * Sem nenhum, o movimento ficava a pairar sem mexer em saldo. Com os
     * dois, o serviço escolhia a conta e ignorava o caixa em silêncio.
     *
     * @test
     */
    public function nem_sem_destino_nem_com_os_dois(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $this->postJson(self::RAIZ, $this->corpo())
            ->assertStatus(422)->assertJsonValidationErrors('account_id');

        $this->postJson(self::RAIZ, $this->corpo([
            'account_id' => $this->conta()->id,
            'cash_register_id' => $this->caixa()->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('account_id');

        $this->assertSame(0, Transaction::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * OMITIR UM CAMPO OPCIONAL NÃO É ERRO DE SERVIDOR.
     *
     * Uma regra `nullable` que não recebe nada não põe a chave no array
     * validado. Lê-la directamente dava 500 a qualquer cliente que
     * simplesmente não mandasse o campo — e o ecrã manda-os todos, por isso
     * isto só aparecia a quem chamasse a API de outro sítio.
     *
     * @test
     */
    public function um_campo_opcional_que_nao_vem_nao_da_erro_de_servidor(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta();

        // Sem `cash_register_id`, sem `transaction_category_id`, sem
        // `reference` e sem `notes` — nem sequer a null.
        $this->postJson(self::RAIZ, [
            'transaction_type_id' => $this->tipo()->id,
            'amount' => 250,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => $this->forma()->id,
            'account_id' => $conta->id,
            'description' => 'O mínimo que se pode mandar',
            'status' => 'completed',
        ])->assertCreated();

        $this->assertEqualsWithDelta(250, $conta->fresh()->current_balance, 0.01);
    }

    /* ─── Corrigir ────────────────────────────────────────────────────── */

    /**
     * CORRIGIR DESFAZ O ANTIGO E FAZ O NOVO.
     *
     * Mudar o valor E a conta de destino de um movimento já lançado: a conta
     * antiga tem de ficar como estava, e a nova receber o valor novo. Sem
     * desfazer com os valores antigos, a antiga ficava inflada para sempre.
     *
     * @test
     */
    public function corrigir_devolve_a_conta_antiga_e_carrega_a_nova(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create', 'treasury.transactions.edit');

        $antiga = $this->conta(['current_balance' => 0]);
        $nova = $this->conta(['current_balance' => 0]);

        $id = $this->postJson(self::RAIZ, $this->corpo(['account_id' => $antiga->id, 'amount' => 5000]))
            ->assertCreated()->json('id');

        $this->assertEqualsWithDelta(5000, $antiga->fresh()->current_balance, 0.01);

        $this->putJson(self::RAIZ . '/' . $id, $this->corpo([
            'transaction_type_id' => Transaction::findOrFail($id)->transaction_type_id,
            'account_id' => $nova->id,
            'amount' => 1200,
        ]))->assertOk();

        $this->assertEqualsWithDelta(0, $antiga->fresh()->current_balance, 0.01, 'a conta antiga volta ao que era');
        $this->assertEqualsWithDelta(1200, $nova->fresh()->current_balance, 0.01, 'a nova recebe o valor novo');
    }

    /** Apagar um movimento concluído devolve o dinheiro. @test */
    public function apagar_devolve_o_saldo(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create', 'treasury.transactions.delete');

        $conta = $this->conta(['current_balance' => 0]);

        $id = $this->postJson(self::RAIZ, $this->corpo(['account_id' => $conta->id, 'amount' => 4000]))
            ->assertCreated()->json('id');

        $this->deleteJson(self::RAIZ . '/' . $id)->assertOk();

        $this->assertEqualsWithDelta(0, $conta->fresh()->current_balance, 0.01);
        $this->assertNull(Transaction::find($id));
    }

    /* ─── Estornar ────────────────────────────────────────────────────── */

    /** @test */
    public function estornar_cria_a_saida_e_devolve_o_saldo(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta(['current_balance' => 0]);

        $id = $this->postJson(self::RAIZ, $this->corpo(['account_id' => $conta->id, 'amount' => 7000]))
            ->assertCreated()->json('id');

        $original = Transaction::findOrFail($id);

        $r = $this->postJson(self::RAIZ . '/' . $id . '/creditar')->assertCreated();

        $estorno = Transaction::findOrFail($r->json('id'));

        $this->assertSame('expense', $estorno->type);
        $this->assertSame('credit_note', $estorno->category);
        $this->assertSame('CREDIT-' . $original->transaction_number, $estorno->reference);
        $this->assertEqualsWithDelta(0, $conta->fresh()->current_balance, 0.01, 'o dinheiro voltou');

        // O NÚMERO DO ESTORNO SAI DA SEQUÊNCIA. O ecrã de sempre escrevia
        // `TRX-CREDIT-<uniqid>` no pedido — que o serviço ignorava e ninguém
        // reparou, porque o número gravado nunca era esse.
        $this->assertStringStartsWith('TRX-' . date('Y') . '-', $estorno->transaction_number);
    }

    /** Só entradas se estornam. @test */
    public function uma_saida_nao_se_estorna(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'transaction_type_id' => $this->tipo('expense')->id,
            'account_id' => $this->conta(['current_balance' => 9000])->id,
        ]))->assertCreated()->json('id');

        $this->postJson(self::RAIZ . '/' . $id . '/creditar')->assertStatus(422);
    }

    /**
     * ANULAR UMA VENDA NÃO SE FAZ AQUI.
     *
     * Com factura por trás, o estorno é uma nota de crédito: linhas
     * escolhidas, imposto recalculado, stock reposto, hash SAFT e comunicação
     * à AGT. O servidor recusa — e diz por onde se faz.
     *
     * @test
     */
    public function com_factura_por_tras_o_servidor_manda_emitir_nota_de_credito(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta(['current_balance' => 0]);

        $id = $this->postJson(self::RAIZ, $this->corpo(['account_id' => $conta->id, 'amount' => 3000]))
            ->assertCreated()->json('id');

        // A factura entra depois: o que interessa é o movimento apontar para uma.
        $factura = \App\Models\Invoicing\SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FR ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 3000, 'tax_amount' => 0, 'total' => 3000,
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);

        Transaction::where('id', $id)->update(['invoice_id' => $factura->id]);

        $r = $this->postJson(self::RAIZ . '/' . $id . '/creditar')->assertStatus(409);

        $this->assertStringContainsString('credit_transaction=' . $id, (string) $r->json('redireccionar'));
        $this->assertEqualsWithDelta(3000, $conta->fresh()->current_balance, 0.01, 'nada se mexeu');
        $this->assertSame(1, Transaction::where('tenant_id', $this->tenant->id)->count());
    }

    /* ─── A lista ─────────────────────────────────────────────────────── */

    /**
     * OS TOTAIS SEGUEM OS FILTROS DA LISTA.
     *
     * Somavam tudo desde sempre: filtrar por um mês e ver em cima o total de
     * três anos faz desconfiar dos dois números, e com razão.
     *
     * @test
     */
    public function os_totais_seguem_os_mesmos_filtros_da_lista(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta();
        $entrada = $this->tipo('income');

        foreach ([['2026-01-10', 1000], ['2026-05-20', 2000], ['2026-09-01', 4000]] as [$dia, $valor]) {
            $this->postJson(self::RAIZ, $this->corpo([
                'transaction_type_id' => $entrada->id,
                'account_id' => $conta->id,
                'transaction_date' => $dia,
                'amount' => $valor,
            ]))->assertCreated();
        }

        $tudo = $this->getJson(self::RAIZ)->assertOk();
        $this->assertSame(3, $tudo->json('meta.total'));
        $this->assertEqualsWithDelta(7000, $tudo->json('resumo.entradas'), 0.01);

        $maio = $this->getJson(self::RAIZ . '?de=2026-05-01&ate=2026-05-31')->assertOk();
        $this->assertSame(1, $maio->json('meta.total'));
        $this->assertEqualsWithDelta(2000, $maio->json('resumo.entradas'), 0.01, 'o total segue o período');
        $this->assertEqualsWithDelta(2000, $maio->json('resumo.saldo'), 0.01);
    }

    /** A procura apanha o número, a descrição e a referência. @test */
    public function a_procura_apanha_o_numero_a_descricao_e_a_referencia(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $conta = $this->conta();

        $this->postJson(self::RAIZ, $this->corpo([
            'account_id' => $conta->id, 'description' => 'Aluguer da loja', 'reference' => 'CONTRATO-77',
        ]))->assertCreated();

        $this->postJson(self::RAIZ, $this->corpo([
            'account_id' => $conta->id, 'description' => 'Outra coisa qualquer', 'reference' => 'X-1',
        ]))->assertCreated();

        $this->assertCount(1, $this->getJson(self::RAIZ . '?procura=Aluguer')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson(self::RAIZ . '?procura=CONTRATO-77')->assertOk()->json('data'));
    }

    /** A ficha diz onde o dinheiro caiu e quem o lançou. @test */
    public function a_ficha_diz_o_destino_e_o_autor(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.create');

        $caixa = $this->caixa(['name' => 'Caixa da frente']);

        $id = $this->postJson(self::RAIZ, $this->corpo(['cash_register_id' => $caixa->id]))
            ->assertCreated()->json('id');

        $r = $this->getJson(self::RAIZ . '/' . $id)->assertOk();

        $this->assertSame('Caixa da frente', $r->json('caixa'));
        $this->assertNull($r->json('conta'));
        $this->assertSame($this->user->name, $r->json('criado_por'));
    }

    /** A empresa do lado não se vê nem se toca. @test */
    public function um_movimento_de_outra_empresa_nao_se_alcanca(): void
    {
        $this->comPermissoes('treasury.transactions.view', 'treasury.transactions.edit', 'treasury.transactions.delete');

        $outra = \App\Models\Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);

        $alheio = Transaction::create([
            'tenant_id' => $outra->id,
            'user_id' => $this->user->id,
            'transaction_number' => 'TRX-ALHEIA-1',
            'type' => 'income',
            'amount' => 100,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'description' => 'Da empresa do lado',
            'status' => 'completed',
        ]);

        $this->getJson(self::RAIZ . '/' . $alheio->id)->assertNotFound();
        $this->deleteJson(self::RAIZ . '/' . $alheio->id)->assertNotFound();
        $this->assertNotNull(Transaction::withoutGlobalScopes()->find($alheio->id));
    }
}
