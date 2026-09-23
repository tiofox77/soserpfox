<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\TransactionType;
use App\Models\User;
use App\Services\Treasury\TreasuryMovementService;
use Tests\TenantTestCase;

/**
 * A GAVETA, O TURNO E A TESOURARIA DIZEM O MESMO (23/09/2026).
 *
 * Dois defeitos, contados à loja com a Caixa do Cleiton e a Caixa do Gerente:
 *
 *  A. abrir e fechar o turno ESCREVIA o saldo da caixa por cima, sem movimento:
 *     o dinheiro que ficava na caixa sem ser transferido desaparecia da
 *     tesouraria, e as quebras de caixa nunca apareciam como movimento;
 *  B. o que a tesouraria tirava ou punha na gaveta a meio do turno (a recolha
 *     do gerente, a despesa paga da gaveta, o troco, o fornecedor pago em
 *     numerário) não entrava no turno — o operador fechava com uma falta que
 *     não era dele.
 *
 * A regra que os dois ensaios guardam: no fim, o saldo da caixa é o fundo de
 * maneio mais tudo o que lá está lançado — a mesma conta do caixas:verificar.
 */
class GavetaDoTurnoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private CashRegister $doCleiton;

    private CashRegister $doGerente;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comModulo('treasury')->comPermissoes(
            'treasury.transactions.create', 'treasury.transactions.delete',
            'treasury.transfers.create', 'treasury.transfers.delete',
            'invoicing.receipts.create',
        );

        $this->gerente = User::create([
            'name' => 'Gerente', 'email' => 'gerente' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        // O operador do ensaio é o Cleiton; a caixa dele está fechada (o turno abre-a).
        $this->doCleiton = $this->caixa($this->user, 20000, 'closed');
        $this->doGerente = $this->caixa($this->gerente, 0, 'open');

        PaymentMethod::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CASH'],
            ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true, 'default_cash_register_id' => null],
        );
    }

    private function caixa(User $dono, float $fundo, string $estado): CashRegister
    {
        return CashRegister::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $dono->id,
            'name' => 'Caixa ' . $dono->name, 'code' => 'CX' . random_int(1000, 9999),
            'is_active' => true, 'is_default' => false, 'status' => $estado,
            'opening_balance' => $fundo, 'current_balance' => $fundo, 'expected_balance' => $fundo,
        ]);
    }

    private function saldo(CashRegister $c): float
    {
        return round((float) CashRegister::withoutGlobalScopes()->whereKey($c->id)->value('current_balance'), 2);
    }

    /** A conta do caixas:verificar: fundo de maneio + tudo o que está lançado na caixa. */
    private function assertSaldoBateComOsMovimentos(CashRegister $c): void
    {
        $lancado = (float) Transaction::withoutGlobalScopes()
            ->where('cash_register_id', $c->id)->where('status', 'completed')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) AS s")->value('s');
        $fundo = (float) CashRegister::withoutGlobalScopes()->whereKey($c->id)->value('opening_balance');

        $this->assertEqualsWithDelta($fundo + $lancado, $this->saldo($c), 0.01, 'o saldo da caixa tem de ser o fundo mais os movimentos');
    }

    private function abrirTurno(float $fundo): PosShift
    {
        $id = $this->postJson(self::RAIZ . '/turnos/abrir', ['opening_balance' => $fundo])->assertCreated()->json('turno.id');

        return PosShift::withoutGlobalScopes()->findOrFail($id);
    }

    private function estado(): array
    {
        return $this->getJson(self::RAIZ . '/turnos/estado')->assertOk()->json('turno');
    }

    /** Uma venda a dinheiro como o balcão a faz: no turno e na caixa do operador. */
    private function vendaEmDinheiro(PosShift $turno, float $valor): void
    {
        $turno->addTransaction(['type' => 'invoice', 'payment_method' => 'cash', 'amount' => $valor, 'reference_type' => SalesInvoice::class, 'reference_id' => 0]);
        app(TreasuryMovementService::class)->post([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'income', 'category' => 'cash',
            'amount' => $valor, 'currency' => 'AOA', 'transaction_date' => now(), 'cash_register_id' => $this->doCleiton->id,
            'related_type' => SalesInvoice::class, 'related_id' => 0, 'description' => 'Venda', 'status' => 'completed',
        ]);
    }

    private function acertos(PosShift $turno)
    {
        return Transaction::withoutGlobalScopes()->where('related_type', PosShift::class)->where('related_id', $turno->id)->get();
    }

    private function tipo(string $natureza): TransactionType
    {
        return TransactionType::create([
            'tenant_id' => $this->tenant->id, 'name' => ucfirst($natureza) . ' ' . uniqid(),
            'code' => strtoupper(substr($natureza, 0, 3)) . random_int(100, 999), 'nature' => $natureza, 'is_active' => true,
        ]);
    }

    private function movimentoManual(string $natureza, float $valor, CashRegister $caixa): int
    {
        return $this->postJson(self::RAIZ . '/tesouraria/movimentos', [
            'transaction_type_id' => $this->tipo($natureza)->id, 'amount' => $valor, 'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => PaymentMethod::where('tenant_id', $this->tenant->id)->where('code', 'CASH')->value('id'),
            'cash_register_id' => $caixa->id, 'description' => $natureza === 'income' ? 'Reforço de troco' : 'Água para a loja',
            'status' => 'completed',
        ])->assertCreated()->json('id');
    }

    /* ─── B. O que sai e entra na gaveta pela tesouraria ──────────────── */

    public function test_a_recolha_do_gerente_a_meio_do_turno_sai_do_esperado_do_operador(): void
    {
        $turno = $this->abrirTurno(20000);
        $this->vendaEmDinheiro($turno, 100000);

        $this->postJson(self::RAIZ . '/tesouraria/transferencias', [
            'de' => 'cash:' . $this->doCleiton->id, 'para' => 'cash:' . $this->doGerente->id,
            'amount' => 80000, 'transfer_date' => now()->toDateString(),
        ])->assertCreated();

        $e = $this->estado();
        $this->assertEqualsWithDelta(40000, $e['expected_cash'], 0.01, '20.000 + 100.000 vendidos − 80.000 recolhidos');
        $this->assertEqualsWithDelta(80000, $e['saidas_da_gaveta'], 0.01);
        $this->assertEqualsWithDelta(100000, $e['total_sales'], 0.01, 'a recolha não é uma venda a menos');
        $this->assertEqualsWithDelta(100000, $e['cash_sales'], 0.01);

        // Conta os 40.000 que lá estão: não há falta nenhuma.
        $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 40000])->assertOk()
            ->assertJsonPath('turno.cash_difference', 0);

        $this->assertCount(0, $this->acertos($turno), 'sem diferença não há acerto');
        $this->assertEqualsWithDelta(40000, $this->saldo($this->doCleiton), 0.01);
        $this->assertSame('closed', CashRegister::withoutGlobalScopes()->find($this->doCleiton->id)->status);
        $this->assertSaldoBateComOsMovimentos($this->doCleiton);
        $this->assertSaldoBateComOsMovimentos($this->doGerente);
    }

    public function test_anular_a_transferencia_devolve_o_esperado(): void
    {
        $turno = $this->abrirTurno(20000);
        $this->vendaEmDinheiro($turno, 100000);

        $id = $this->postJson(self::RAIZ . '/tesouraria/transferencias', [
            'de' => 'cash:' . $this->doCleiton->id, 'para' => 'cash:' . $this->doGerente->id,
            'amount' => 80000, 'transfer_date' => now()->toDateString(),
        ])->assertCreated()->json('id');

        $this->deleteJson(self::RAIZ . '/tesouraria/transferencias/' . $id)->assertOk();

        $e = $this->estado();
        $this->assertEqualsWithDelta(0, $e['saidas_da_gaveta'], 0.01);
        $this->assertEqualsWithDelta(120000, $e['expected_cash'], 0.01);
    }

    public function test_a_despesa_paga_da_gaveta_e_o_reforco_de_troco(): void
    {
        $this->abrirTurno(20000);

        $despesa = $this->movimentoManual('expense', 5000, $this->doCleiton);
        $this->movimentoManual('income', 2000, $this->doCleiton);

        $e = $this->estado();
        $this->assertEqualsWithDelta(5000, $e['saidas_da_gaveta'], 0.01);
        $this->assertEqualsWithDelta(2000, $e['entradas_na_gaveta'], 0.01);
        $this->assertEqualsWithDelta(17000, $e['expected_cash'], 0.01);

        // Apagar a despesa devolve-a ao esperado.
        $this->deleteJson(self::RAIZ . '/tesouraria/movimentos/' . $despesa)->assertOk();
        $this->assertEqualsWithDelta(22000, $this->estado()['expected_cash'], 0.01);
        $this->assertSaldoBateComOsMovimentos($this->doCleiton);
    }

    public function test_um_movimento_na_caixa_de_outra_pessoa_nao_mexe_no_turno(): void
    {
        $this->abrirTurno(20000);

        $this->movimentoManual('expense', 5000, $this->doGerente);

        $e = $this->estado();
        $this->assertEqualsWithDelta(0, $e['saidas_da_gaveta'], 0.01);
        $this->assertEqualsWithDelta(20000, $e['expected_cash'], 0.01);
    }

    public function test_o_fornecedor_pago_em_numerario_sai_da_gaveta(): void
    {
        $this->abrirTurno(20000);

        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Padaria do Bairro', 'is_active' => true]);
        $compra = PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id, 'supplier_id' => $fornecedor->id, 'invoice_number' => 'FC-' . uniqid(),
            'invoice_date' => now(), 'due_date' => now()->addDays(30), 'subtotal' => 3000, 'total' => 3000, 'paid_amount' => 0, 'status' => 'pending',
        ]);

        $this->postJson(self::RAIZ . '/pagamentos/purchase/' . $compra->id, ['amount' => 3000, 'payment_method' => 'cash'])->assertSuccessful();

        $e = $this->estado();
        $this->assertEqualsWithDelta(3000, $e['saidas_da_gaveta'], 0.01);
        $this->assertEqualsWithDelta(17000, $e['expected_cash'], 0.01);
        $this->assertSaldoBateComOsMovimentos($this->doCleiton);
    }

    /* ─── A. Abrir e fechar já não escrevem o saldo por cima ──────────── */

    public function test_o_dinheiro_que_ficou_na_caixa_aparece_na_abertura(): void
    {
        // Ontem vendeu-se 130.000 e ninguém transferiu para a Caixa do Gerente.
        app(TreasuryMovementService::class)->post([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'income', 'category' => 'cash',
            'amount' => 130000, 'currency' => 'AOA', 'transaction_date' => now()->subDay(), 'cash_register_id' => $this->doCleiton->id,
            'description' => 'Vendas de ontem', 'status' => 'completed',
        ]);
        $this->assertEqualsWithDelta(150000, $this->saldo($this->doCleiton), 0.01);

        $turno = $this->abrirTurno(20000);

        $acerto = $this->acertos($turno)->sole();
        $this->assertSame('expense', $acerto->type);
        $this->assertSame('cash_adjustment', $acerto->category);
        $this->assertEqualsWithDelta(130000, (float) $acerto->amount, 0.01);
        $this->assertStringContainsString('Falta na abertura', $acerto->description);

        $caixa = CashRegister::withoutGlobalScopes()->find($this->doCleiton->id);
        $this->assertEqualsWithDelta(20000, (float) $caixa->current_balance, 0.01, 'a caixa fica com o que foi contado');
        $this->assertEqualsWithDelta(20000, (float) $caixa->opening_balance, 0.01, 'o fundo de maneio configurado não se toca');
        $this->assertSame('open', $caixa->status);
        $this->assertSaldoBateComOsMovimentos($this->doCleiton);
    }

    public function test_a_quebra_do_fecho_fica_lancada(): void
    {
        $turno = $this->abrirTurno(20000);
        $this->vendaEmDinheiro($turno, 100000);

        $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 119000, 'difference_reason' => 'troco mal dado'])->assertOk()
            ->assertJsonPath('turno.cash_difference', -1000);

        $acerto = $this->acertos($turno)->sole();
        $this->assertSame('expense', $acerto->type);
        $this->assertEqualsWithDelta(1000, (float) $acerto->amount, 0.01);
        $this->assertStringContainsString('Quebra de caixa', $acerto->description);
        $this->assertSame('troco mal dado', $acerto->notes);

        $this->assertEqualsWithDelta(119000, $this->saldo($this->doCleiton), 0.01);
        $this->assertSaldoBateComOsMovimentos($this->doCleiton);
    }

    public function test_os_acertos_nao_entram_no_turno(): void
    {
        $turno = $this->abrirTurno(20000);
        $this->vendaEmDinheiro($turno, 100000);
        $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 125000])->assertOk();

        // A sobra de 5.000 foi à tesouraria; o turno contou-a como diferença, não como entrada.
        $this->assertSame(0, $turno->transactions()->withoutGlobalScopes()->whereIn('type', PosShift::TIPOS_DA_GAVETA)->count());
        $this->assertStringContainsString('Sobra de caixa', $this->acertos($turno)->sole()->description);
    }

    /* ─── O PWA pela mesma porta ──────────────────────────────────────── */

    public function test_o_turno_do_pwa_abre_e_fecha_a_caixa_do_operador(): void
    {
        $this->comPermissoesDoPwa();

        $this->postJson('/api/v1/invoicing/pos/shift/open', ['opening_balance' => 20000])->assertCreated();
        $this->assertSame('open', CashRegister::withoutGlobalScopes()->find($this->doCleiton->id)->status,
            'com a caixa fechada, o numerário das vendas do PWA ia para a gaveta de outra pessoa');

        $this->postJson('/api/v1/invoicing/pos/shift/close', ['actual_cash' => 19500])->assertOk();

        $caixa = CashRegister::withoutGlobalScopes()->find($this->doCleiton->id);
        $this->assertSame('closed', $caixa->status);
        $this->assertEqualsWithDelta(19500, (float) $caixa->current_balance, 0.01);
        $this->assertSaldoBateComOsMovimentos($this->doCleiton);
    }
}
