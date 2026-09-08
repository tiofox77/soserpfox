<?php

namespace Tests\Feature;

use App\Models\Treasury\Account;
use App\Models\Treasury\Bank;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\Transfer;
use Tests\TenantTestCase;

/**
 * MOVER DINHEIRO ENTRE CONTAS E CAIXAS — a API.
 *
 * O que este ensaio guarda:
 *
 * 1. **AS TRÊS PERNAS OU NENHUMA.** Saída na origem, entrada no destino e a
 *    taxa — também na origem. Todas na mesma transacção da base.
 *
 * 2. **O SALDO MEXE-SE PELO SERVIÇO.** O ecrã de sempre tinha um
 *    `applyBalance()` próprio, com `increment` solto e sem bloqueio: a
 *    TERCEIRA implementação do mesmo facto na tesouraria.
 *
 * 3. **A ORIGEM E O DESTINO SÃO DESTA EMPRESA.** O ecrã mandava
 *    `"account:5"` e o servidor acreditava. Com um id de outra empresa
 *    nascia a transferência, nasciam os movimentos, e o saldo não mexia em
 *    lado nenhum — uma transferência a fingir, sem erro nenhum.
 *
 * 4. **ANULAR DESFAZ O QUE ESTÁ LÁ**, e não o que se supõe ter sido feito.
 *
 * 5. **O NÚMERO NÃO REINICIA.** `substr(-4) + 1` sobre a última linha por id
 *    é o padrão que envenenou a sequência dos movimentos — e a coluna tem
 *    índice único por empresa.
 */
class ApiDasTransferenciasDaTesourariaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/tesouraria/transferencias';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('treasury');
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function conta(float $saldo = 0, array $por = []): Account
    {
        $banco = Bank::firstOrCreate(
            ['code' => 'BFA'],
            ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]
        );

        return Account::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'bank_id' => $banco->id,
            'account_name' => 'Conta ' . uniqid(),
            'account_number' => (string) random_int(100000, 999999),
            'currency' => 'AOA',
            'initial_balance' => $saldo,
            'current_balance' => $saldo,
            'is_active' => true,
        ], $por));
    }

    private function caixa(float $saldo = 0): CashRegister
    {
        return CashRegister::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Caixa ' . uniqid(),
            'code' => 'CX' . random_int(1000, 9999),
            'opening_balance' => $saldo,
            'current_balance' => $saldo,
            'status' => 'open',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function corpo(string $de, string $para, array $por = []): array
    {
        return array_merge([
            'de' => $de,
            'para' => $para,
            'amount' => 10000,
            'fee' => 0,
            'currency' => 'AOA',
            'transfer_date' => now()->toDateString(),
            'description' => 'Reforço do caixa',
        ], $por);
    }

    /* ─── As permissões ───────────────────────────────────────────────── */

    /** @test */
    public function sem_permissao_nao_se_ve_nem_se_transfere(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo('account:1', 'cash:1'))->assertForbidden();
    }

    /**
     * VER NÃO É TRANSFERIR, E TRANSFERIR NÃO É ANULAR.
     *
     * Anular devolve dinheiro já movido. A morada de sempre não exigia
     * permissão nenhuma — bastava ter o módulo activo.
     *
     * @test
     */
    public function ver_nao_da_direito_a_transferir_nem_a_anular(): void
    {
        $this->comPermissoes('treasury.transfers.view');

        $conta = $this->conta(50000);
        $caixa = $this->caixa();

        $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}"))->assertForbidden();

        $t = Transfer::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'from_account_id' => $conta->id, 'to_cash_register_id' => $caixa->id,
            'transfer_number' => 'TRF-TESTE-1', 'amount' => 100, 'currency' => 'AOA',
            'fee' => 0, 'transfer_date' => now()->toDateString(), 'status' => 'completed',
        ]);

        $this->deleteJson(self::RAIZ . '/' . $t->id)->assertForbidden();
    }

    /** As opções trazem os dois bolsos com o saldo à vista. @test */
    public function as_opcoes_trazem_contas_e_caixas_com_o_saldo(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(75000);
        $caixa = $this->caixa(1200);

        $r = $this->getJson(self::RAIZ . '/opcoes')->assertOk();

        $this->assertEqualsWithDelta(
            75000,
            collect($r->json('contas'))->firstWhere('id', $conta->id)['saldo'],
            0.01,
            'escolher de onde sai o dinheiro sem ver quanto lá está é escolher às cegas'
        );

        $this->assertEqualsWithDelta(1200, collect($r->json('caixas'))->firstWhere('id', $caixa->id)['saldo'], 0.01);
        $this->assertTrue($r->json('permissoes.pode_criar'));
        $this->assertFalse($r->json('permissoes.pode_anular'));
    }

    /* ─── Transferir ──────────────────────────────────────────────────── */

    /**
     * AS DUAS PERNAS, E OS DOIS SALDOS.
     *
     * @test
     */
    public function transferir_tira_da_origem_e_poe_no_destino(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(50000);
        $caixa = $this->caixa(0);

        $r = $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", ['amount' => 12000]))
            ->assertCreated();

        $this->assertStringStartsWith('TRF-' . date('Y') . '-', (string) $r->json('numero'));

        $this->assertEqualsWithDelta(38000, $conta->fresh()->current_balance, 0.01);
        $this->assertEqualsWithDelta(12000, $caixa->fresh()->current_balance, 0.01);

        // DUAS PERNAS, ligadas à transferência pelo `related_*`.
        $pernas = Transaction::where('related_type', Transfer::class)
            ->where('related_id', $r->json('id'))->get();

        $this->assertCount(2, $pernas);
        $this->assertSame('expense', $pernas->firstWhere('account_id', $conta->id)->type);
        $this->assertSame('income', $pernas->firstWhere('cash_register_id', $caixa->id)->type);
    }

    /**
     * A TAXA SAI DE QUEM MANDA — e é uma terceira perna.
     *
     * @test
     */
    public function a_taxa_sai_da_origem_e_nao_chega_ao_destino(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(50000);
        $caixa = $this->caixa(0);

        $id = $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", [
            'amount' => 10000, 'fee' => 250,
        ]))->assertCreated()->json('id');

        $this->assertEqualsWithDelta(39750, $conta->fresh()->current_balance, 0.01, '50000 − 10000 − 250');
        $this->assertEqualsWithDelta(10000, $caixa->fresh()->current_balance, 0.01, 'a taxa não chega ao destino');

        $pernas = Transaction::where('related_type', Transfer::class)->where('related_id', $id)->get();

        $this->assertCount(3, $pernas);
        $this->assertSame('transfer_fee', $pernas->firstWhere('amount', '250.00')?->category);
    }

    /**
     * A ORIGEM E O DESTINO TÊM DE SER DESTA EMPRESA.
     *
     * Com um id de outra empresa nascia a transferência, nasciam os
     * movimentos — e o saldo não mexia em lado nenhum, porque o `increment`
     * já era filtrado pela empresa. Uma transferência a fingir.
     *
     * @test
     */
    public function uma_conta_de_outra_empresa_nao_serve(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $outra = \App\Models\Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);

        $alheia = $this->conta(9999, ['tenant_id' => $outra->id]);
        $minha = $this->caixa(0);

        $this->postJson(self::RAIZ, $this->corpo("account:{$alheia->id}", "cash:{$minha->id}"))
            ->assertStatus(422)->assertJsonValidationErrors('de');

        $this->assertSame(0, Transfer::where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(0, Transaction::where('tenant_id', $this->tenant->id)->count());
    }

    /** Um bolso desligado também não serve. @test */
    public function um_caixa_desactivado_nao_serve(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(50000);
        $caixa = $this->caixa(0);
        $caixa->update(['is_active' => false]);

        $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}"))
            ->assertStatus(422)->assertJsonValidationErrors('para');
    }

    /** Não se transfere para o mesmo sítio de onde saiu. @test */
    public function a_origem_e_o_destino_tem_de_ser_diferentes(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(50000);

        $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "account:{$conta->id}"))
            ->assertStatus(422)->assertJsonValidationErrors('para');
    }

    /**
     * O NÚMERO SEGUE A SEQUÊNCIA DO ANO, e um número fora do formato não a
     * reinicia.
     *
     * `orderByDesc('id')` + `substr(-4)` dava lixo assim que aparecia um
     * número antigo — e a coluna tem índice único por empresa.
     *
     * @test
     */
    public function um_numero_fora_do_formato_nao_reinicia_a_sequencia(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(50000);
        $caixa = $this->caixa(0);

        $primeiro = $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", ['amount' => 10]))
            ->assertCreated()->json('numero');

        $this->assertSame('TRF-' . date('Y') . '-0001', $primeiro);

        // Uma linha antiga, de um gerador que já não existe.
        Transfer::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'from_account_id' => $conta->id, 'to_cash_register_id' => $caixa->id,
            'transfer_number' => 'TRF-' . strtoupper(uniqid()),
            'amount' => 1, 'currency' => 'AOA', 'fee' => 0,
            'transfer_date' => now()->toDateString(), 'status' => 'completed',
        ]);

        $segundo = $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", ['amount' => 10]))
            ->assertCreated()->json('numero');

        $this->assertSame('TRF-' . date('Y') . '-0002', $segundo, 'a sequência continua, não recomeça');
    }

    /* ─── Anular ──────────────────────────────────────────────────────── */

    /**
     * ANULAR DESFAZ O QUE ESTÁ LÁ.
     *
     * Cada perna é revertida pelo seu próprio valor — a taxa incluída.
     *
     * @test
     */
    public function anular_devolve_tudo_taxa_incluida(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create', 'treasury.transfers.delete');

        $conta = $this->conta(50000);
        $caixa = $this->caixa(0);

        $id = $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", [
            'amount' => 10000, 'fee' => 250,
        ]))->assertCreated()->json('id');

        $this->deleteJson(self::RAIZ . '/' . $id)->assertOk();

        $this->assertEqualsWithDelta(50000, $conta->fresh()->current_balance, 0.01, 'a origem volta ao que era');
        $this->assertEqualsWithDelta(0, $caixa->fresh()->current_balance, 0.01);

        $this->assertNull(Transfer::find($id));
        $this->assertSame(0, Transaction::where('related_type', Transfer::class)->where('related_id', $id)->count());
    }

    /** A transferência da empresa do lado não se anula. @test */
    public function uma_transferencia_de_outra_empresa_nao_se_anula(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.delete');

        $outra = \App\Models\Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'is_active' => true]);

        $alheia = Transfer::create([
            'tenant_id' => $outra->id, 'user_id' => $this->user->id,
            'transfer_number' => 'TRF-ALHEIA-1', 'amount' => 100, 'currency' => 'AOA',
            'fee' => 0, 'transfer_date' => now()->toDateString(), 'status' => 'completed',
        ]);

        $this->deleteJson(self::RAIZ . '/' . $alheia->id)->assertNotFound();
        $this->assertNotNull(Transfer::withoutGlobalScopes()->find($alheia->id));
    }

    /* ─── A lista ─────────────────────────────────────────────────────── */

    /** @test */
    public function a_lista_procura_e_soma(): void
    {
        $this->comPermissoes('treasury.transfers.view', 'treasury.transfers.create');

        $conta = $this->conta(90000);
        $caixa = $this->caixa(0);

        $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", [
            'amount' => 5000, 'fee' => 100, 'description' => 'Reforço da manhã',
        ]))->assertCreated();

        $this->postJson(self::RAIZ, $this->corpo("account:{$conta->id}", "cash:{$caixa->id}", [
            'amount' => 3000, 'description' => 'Outra coisa',
        ]))->assertCreated();

        $r = $this->getJson(self::RAIZ)->assertOk();

        $this->assertSame(2, $r->json('meta.total'));
        $this->assertEqualsWithDelta(8000, $r->json('resumo.movido'), 0.01);
        $this->assertEqualsWithDelta(100, $r->json('resumo.taxas'), 0.01, 'as taxas são dinheiro que sai e não chega a lado nenhum');

        $manha = $this->getJson(self::RAIZ . '?procura=manhã')->assertOk();
        $this->assertSame(1, $manha->json('meta.total'));
        $this->assertSame('Reforço da manhã', $manha->json('data.0.descricao'));

        // A lista diz de onde para onde, e o que é banco e o que é caixa.
        $this->assertFalse($manha->json('data.0.de_e_caixa'));
        $this->assertTrue($manha->json('data.0.para_e_caixa'));
    }
}
