<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Services\Treasury\TreasuryMovementService;
use Tests\TenantTestCase;

/**
 * A CAIXA DO OPERADOR, DA ABERTURA AO FECHO (19/09/2026).
 *
 * «Quando atribuo ao operador tem um bug, principalmente dinheiro numerário.»
 * O ecrã das Caixas criava-as e o dinheiro nunca chegava lá:
 *
 *  · o OPERADOR aparecia como opcional e a coluna é obrigatória na base —
 *    criar uma caixa sem escolher ninguém rebentava com erro de servidor;
 *  · a caixa nascia FECHADA (omissão da coluna) e o formulário não tinha
 *    onde a abrir, e o `destination()` só aceita caixas abertas: o numerário
 *    não entrava em caixa nenhuma;
 *  · o FUNDO DE MANEIO escrevia-se em `opening_balance` e o saldo ficava a
 *    zero — a gaveta mostrava 0 com dinheiro dentro;
 *  · nem a hora da abertura nem a do fecho eram gravadas.
 */
class CaixaDoOperadorTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/catalogos/caixas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('treasury')->comPermissoes(
            'treasury.cash-registers.view',
            'treasury.cash-registers.create',
            'treasury.cash-registers.edit',
        );
    }

    public function test_a_caixa_nasce_aberta_com_o_fundo_de_maneio_na_gaveta(): void
    {
        $this->postJson(self::RAIZ, [
            'name' => 'Balcão 1', 'code' => 'CX1',
            'user_id' => $this->user->id, 'opening_balance' => 5000,
            'is_active' => true,
        ])->assertCreated();

        $caixa = CashRegister::withoutGlobalScopes()->where('code', 'CX1')->firstOrFail();

        $this->assertSame('open', $caixa->status, 'a caixa nova tem de nascer aberta');
        $this->assertNotNull($caixa->opened_at, 'a hora da abertura fica gravada');
        $this->assertEqualsWithDelta(5000, (float) $caixa->current_balance, 0.01,
            'o fundo de maneio é dinheiro que já está na gaveta');
        $this->assertEqualsWithDelta(5000, (float) $caixa->expected_balance, 0.01);
    }

    public function test_uma_caixa_sem_operador_e_recusada_com_jeito(): void
    {
        $this->postJson(self::RAIZ, [
            'name' => 'Sem dono', 'code' => 'CX0', 'opening_balance' => 0, 'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_o_numerario_cai_na_caixa_do_operador_acabada_de_criar(): void
    {
        $this->postJson(self::RAIZ, [
            'name' => 'Balcão 1', 'code' => 'CX1',
            'user_id' => $this->user->id, 'opening_balance' => 0, 'is_active' => true,
        ])->assertCreated();

        $caixa = CashRegister::withoutGlobalScopes()->where('code', 'CX1')->firstOrFail();

        $metodo = PaymentMethod::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CASH'],
            ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true],
        );

        $destino = app(TreasuryMovementService::class)
            ->destination($metodo, $this->tenant->id, null, null, $this->user->id);

        $this->assertSame($caixa->id, $destino['cash_register_id']);
    }

    public function test_fechar_e_reabrir_a_caixa_marca_as_horas_sem_mexer_no_dinheiro(): void
    {
        $this->postJson(self::RAIZ, [
            'name' => 'Balcão 1', 'code' => 'CX1',
            'user_id' => $this->user->id, 'opening_balance' => 1000, 'is_active' => true,
        ])->assertCreated();

        $caixa = CashRegister::withoutGlobalScopes()->where('code', 'CX1')->firstOrFail();

        // Entrou uma venda: a gaveta tem mais do que o fundo.
        $caixa->forceFill(['current_balance' => 7500])->save();

        $this->putJson(self::RAIZ . '/' . $caixa->id, [
            'name' => 'Balcão 1', 'code' => 'CX1', 'status' => 'closed',
            'user_id' => $this->user->id, 'opening_balance' => 1000, 'is_active' => true,
        ])->assertOk();

        $caixa->refresh();
        $this->assertSame('closed', $caixa->status);
        $this->assertNotNull($caixa->closed_at, 'a hora do fecho fica gravada');
        $this->assertEqualsWithDelta(7500, (float) $caixa->current_balance, 0.01,
            'fechar a caixa não apaga o dinheiro que lá está');

        $this->putJson(self::RAIZ . '/' . $caixa->id, [
            'name' => 'Balcão 1', 'code' => 'CX1', 'status' => 'open',
            'user_id' => $this->user->id, 'opening_balance' => 1000, 'is_active' => true,
        ])->assertOk();

        $caixa->refresh();
        $this->assertSame('open', $caixa->status);
        $this->assertNull($caixa->closed_at, 'reabrir limpa a marca do fecho');
        $this->assertEqualsWithDelta(7500, (float) $caixa->current_balance, 0.01,
            'reabrir também não inventa nem apaga dinheiro');
    }

    public function test_sem_caixa_aberta_o_dinheiro_ainda_tem_dono(): void
    {
        $fechada = CashRegister::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Única', 'code' => 'CXU',
            'user_id' => $this->user->id, 'is_active' => true, 'is_default' => true,
            'status' => 'closed', 'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);

        $metodo = PaymentMethod::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CASH'],
            ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true],
        );

        $destino = app(TreasuryMovementService::class)
            ->destination($metodo, $this->tenant->id, null, null, $this->user->id);

        // Antes ficava `null`: o movimento entrava na tesouraria e desaparecia
        // de todos os mapas de caixa.
        $this->assertSame($fechada->id, $destino['cash_register_id']);
    }
}
