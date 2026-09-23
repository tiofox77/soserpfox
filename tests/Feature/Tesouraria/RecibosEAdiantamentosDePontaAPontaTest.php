<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Client;
use App\Models\Invoicing\Advance;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Services\Invoicing\ContaCorrenteQuery;
use App\Services\Treasury\RelatoriosDeTesouraria;
use Tests\TenantTestCase;

/**
 * RECIBOS E ADIANTAMENTOS, DE PONTA A PONTA (23/09/2026).
 *
 * «Verifica se tudo marca tesouraria, históricos e fluxo de caixa.» Cada
 * dinheiro que entra tem de aparecer, uma vez e só uma, em cinco sítios:
 *
 *  · na TESOURARIA (o movimento, na caixa do operador);
 *  · no TURNO (o fecho de caixa);
 *  · no FLUXO DE CAIXA;
 *  · no EXTRACTO do cliente (a conta corrente);
 *  · na FACTURA (o que falta receber).
 */
class RecibosEAdiantamentosDePontaAPontaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private Client $comprador;

    private CashRegister $gaveta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comModulo('treasury')->comPermissoes(
            'invoicing.receipts.view', 'invoicing.receipts.create',
            'invoicing.advances.create', 'invoicing.advances.edit',
        );

        $this->comprador = $this->clienteEmpresa();

        foreach (['receipt' => 'RC', 'advance' => 'ADT'] as $tipo => $codigo) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => $codigo, 'name' => "{$codigo} (teste)",
                'document_type' => $tipo, 'agt_environment' => 'sandbox', 'is_default' => true, 'is_active' => true,
            ]);
        }

        PaymentMethod::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CASH'],
            ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true, 'default_cash_register_id' => null],
        );

        $this->gaveta = CashRegister::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'name' => 'Caixa do operador', 'code' => 'CX' . random_int(1000, 9999),
            'is_active' => true, 'is_default' => false, 'status' => 'open',
            'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function factura(float $total): SalesInvoice
    {
        $f = new SalesInvoice([
            'client_id' => $this->comprador->id, 'invoice_number' => 'FT ENSAIO/' . random_int(100000, 999999),
            'invoice_date' => now()->toDateString(), 'invoice_type' => 'FT', 'status' => 'sent',
            'subtotal' => $total, 'total' => $total, 'paid_amount' => 0, 'created_by' => $this->user->id,
        ]);
        $f->tenant_id = $this->tenant->id;
        $f->save();

        return $f;
    }

    private function turno(): PosShift
    {
        return PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now()->subHour(), 'opening_balance' => 0,
        ], $this->tenant->id);
    }

    /** O que a tesouraria tem de entradas e saídas — a soma assinada. */
    private function naTesouraria(): float
    {
        return round((float) Transaction::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('status', 'completed')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) AS s")->value('s'), 2);
    }

    private function naGaveta(): float
    {
        return round((float) CashRegister::withoutGlobalScopes()->whereKey($this->gaveta->id)->value('current_balance'), 2);
    }

    private function noTurno(PosShift $t): float
    {
        return round($t->fresh()->dinheiroEsperado(), 2);
    }

    private function noFluxoDeCaixa(): float
    {
        $f = (new RelatoriosDeTesouraria($this->tenant->id, now()->subMonth()->toDateString(), now()->toDateString()))->fluxoDeCaixa();

        return round((float) $f['totalIncome'] - (float) $f['totalExpense'], 2);
    }

    /** O saldo da conta corrente do cliente: positivo = deve, negativo = tem crédito. */
    private function noExtracto(): float
    {
        return round((float) (new ContaCorrenteQuery($this->tenant->id, ContaCorrenteQuery::CLIENTE, $this->comprador->id))->resumo()['saldo_final'], 2);
    }

    private function adiantamento(float $valor): int
    {
        return $this->postJson(self::RAIZ . '/adiantamentos', [
            'client_id' => $this->comprador->id, 'payment_date' => now()->toDateString(),
            'amount' => $valor, 'payment_method' => 'cash', 'purpose' => 'Sinal',
        ])->assertSuccessful()->json('data.id');
    }

    /* ─── Recibo ──────────────────────────────────────────────────────── */

    public function test_o_recibo_marca_em_todo_o_lado_uma_so_vez(): void
    {
        $turno = $this->turno();
        $factura = $this->factura(10000);

        $this->postJson(self::RAIZ . '/recibos', [
            'type' => 'sale', 'client_id' => $this->comprador->id, 'invoice_id' => $factura->id,
            'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'amount_paid' => 4000,
        ])->assertSuccessful();

        $this->assertEqualsWithDelta(4000, $this->naTesouraria(), 0.01, 'tesouraria');
        $this->assertEqualsWithDelta(4000, $this->naGaveta(), 0.01, 'a caixa do operador');
        $this->assertEqualsWithDelta(4000, $this->noTurno($turno), 0.01, 'turno');
        $this->assertEqualsWithDelta(4000, $this->noFluxoDeCaixa(), 0.01, 'fluxo de caixa');
        $this->assertEqualsWithDelta(6000, $this->noExtracto(), 0.01, 'o cliente deve 10.000 − 4.000');
        $this->assertEqualsWithDelta(6000, (float) $factura->fresh()->balance, 0.01, 'a factura');
    }

    public function test_o_recibo_apagado_sai_de_todo_o_lado_turno_incluido(): void
    {
        $turno = $this->turno();
        $factura = $this->factura(10000);

        $id = $this->postJson(self::RAIZ . '/recibos', [
            'type' => 'sale', 'client_id' => $this->comprador->id, 'invoice_id' => $factura->id,
            'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'amount_paid' => 4000,
        ])->assertSuccessful()->json('id');

        Receipt::withoutGlobalScopes()->findOrFail($id)->delete();

        $this->assertEqualsWithDelta(0, $this->naTesouraria(), 0.01);
        $this->assertEqualsWithDelta(0, $this->naGaveta(), 0.01);
        $this->assertEqualsWithDelta(0, $this->noTurno($turno), 0.01, 'o turno não pode esperar o dinheiro de um recibo que já não existe');
        $this->assertEqualsWithDelta(10000, $this->noExtracto(), 0.01);
        $this->assertEqualsWithDelta(10000, (float) $factura->fresh()->balance, 0.01);
    }

    /* ─── Pagamento com excedente ─────────────────────────────────────── */

    public function test_o_excedente_de_um_pagamento_vira_adiantamento_sem_contar_duas_vezes(): void
    {
        $turno = $this->turno();
        $factura = $this->factura(7000);

        $this->postJson(self::RAIZ . '/pagamentos/sale/' . $factura->id, ['amount' => 10000, 'payment_method' => 'cash'])->assertSuccessful();

        $sobra = Advance::withoutGlobalScopes()->where('client_id', $this->comprador->id)->sole();
        $this->assertEqualsWithDelta(3000, (float) $sobra->remaining_amount, 0.01);

        // Entraram 10.000 na gaveta — nem mais, nem menos.
        $this->assertEqualsWithDelta(10000, $this->naTesouraria(), 0.01, 'tesouraria');
        $this->assertEqualsWithDelta(10000, $this->naGaveta(), 0.01);
        $this->assertEqualsWithDelta(10000, $this->noTurno($turno), 0.01, 'turno');
        $this->assertEqualsWithDelta(10000, $this->noFluxoDeCaixa(), 0.01, 'fluxo de caixa');

        // A factura fica paga, e não com saldo negativo; o cliente tem 3.000 de crédito — uma vez.
        $this->assertEqualsWithDelta(0, (float) $factura->fresh()->balance, 0.01, 'a factura não fica paga a mais');
        $this->assertSame('paid', $factura->fresh()->status);
        $this->assertEqualsWithDelta(-3000, $this->noExtracto(), 0.01, 'o crédito do cliente é o excedente, e não o dobro');
    }

    /* ─── Adiantamento ────────────────────────────────────────────────── */

    public function test_o_adiantamento_marca_em_todo_o_lado(): void
    {
        $turno = $this->turno();

        $this->adiantamento(5000);

        $this->assertEqualsWithDelta(5000, $this->naTesouraria(), 0.01);
        $this->assertEqualsWithDelta(5000, $this->naGaveta(), 0.01);
        $this->assertEqualsWithDelta(5000, $this->noTurno($turno), 0.01);
        $this->assertEqualsWithDelta(5000, $this->noFluxoDeCaixa(), 0.01);
        $this->assertEqualsWithDelta(-5000, $this->noExtracto(), 0.01, 'o cliente tem 5.000 de crédito');
    }

    public function test_usar_o_adiantamento_numa_factura_nao_o_conta_outra_vez(): void
    {
        $turno = $this->turno();
        $id = $this->adiantamento(5000);
        $factura = $this->factura(8000);

        // Paga 3.000 em dinheiro e 5.000 do adiantamento.
        $this->postJson(self::RAIZ . '/pagamentos/sale/' . $factura->id, [
            'amount' => 3000, 'payment_method' => 'cash', 'advance_id' => $id, 'advance_amount' => 5000,
        ])->assertSuccessful();

        $this->assertEqualsWithDelta(0, (float) $factura->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(8000, $this->naTesouraria(), 0.01, 'o adiantamento já tinha entrado: só entram os 3.000');
        $this->assertEqualsWithDelta(8000, $this->noTurno($turno), 0.01);
        $this->assertEqualsWithDelta(8000, $this->noFluxoDeCaixa(), 0.01);
        $this->assertEqualsWithDelta(0, $this->noExtracto(), 0.01, 'factura paga e adiantamento gasto: o cliente não deve nem tem crédito');
    }

    public function test_editar_o_adiantamento_no_mesmo_turno_nao_o_conta_duas_vezes(): void
    {
        $turno = $this->turno();
        $id = $this->adiantamento(5000);

        $this->putJson(self::RAIZ . '/adiantamentos/' . $id, [
            'client_id' => $this->comprador->id, 'payment_date' => now()->toDateString(),
            'amount' => 6000, 'payment_method' => 'cash', 'purpose' => 'Sinal corrigido',
        ])->assertSuccessful();

        $this->assertEqualsWithDelta(6000, $this->naTesouraria(), 0.01);
        $this->assertEqualsWithDelta(6000, $this->naGaveta(), 0.01);
        $this->assertEqualsWithDelta(6000, $this->noTurno($turno), 0.01, 'o turno tinha os 5.000 antigos E os 6.000 novos');
        $this->assertEqualsWithDelta(-6000, $this->noExtracto(), 0.01);
    }

    /* ─── Fornecedores e travões ──────────────────────────────────────── */

    private function compra(float $total): \App\Models\Invoicing\PurchaseInvoice
    {
        $fornecedor = \App\Models\Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Grossista ' . uniqid(), 'is_active' => true]);

        return \App\Models\Invoicing\PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id, 'supplier_id' => $fornecedor->id, 'invoice_number' => 'FC-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => $total, 'total' => $total, 'paid_amount' => 0, 'status' => 'pending',
        ]);
    }

    public function test_o_pagamento_ao_fornecedor_conta_uma_vez_no_extracto_dele(): void
    {
        $compra = $this->compra(9000);
        $extracto = fn () => round(abs((float) (new ContaCorrenteQuery($this->tenant->id, ContaCorrenteQuery::FORNECEDOR, $compra->supplier_id))->resumo()['saldo_final']), 2);

        $this->assertEqualsWithDelta(9000, $extracto(), 0.01);

        $this->postJson(self::RAIZ . '/pagamentos/purchase/' . $compra->id, ['amount' => 4000, 'payment_method' => 'cash'])->assertSuccessful();

        $this->assertEqualsWithDelta(5000, $extracto(), 0.01, 'o recibo de compra contava duas vezes: no recibo e na linha «pago sem recibo»');
        $this->assertEqualsWithDelta(-4000, $this->naTesouraria(), 0.01);
        $this->assertEqualsWithDelta(-4000, $this->noFluxoDeCaixa(), 0.01);
    }

    public function test_nao_se_paga_ao_fornecedor_mais_do_que_falta(): void
    {
        $compra = $this->compra(9000);

        $this->postJson(self::RAIZ . '/pagamentos/purchase/' . $compra->id, ['amount' => 10000, 'payment_method' => 'cash'])->assertStatus(422);
        $this->assertEqualsWithDelta(0, $this->naTesouraria(), 0.01, 'nada foi lançado');
    }

    public function test_do_adiantamento_nao_se_usa_mais_do_que_falta(): void
    {
        $id = $this->adiantamento(5000);
        $factura = $this->factura(3000);

        $this->postJson(self::RAIZ . '/pagamentos/sale/' . $factura->id, [
            'amount' => 0, 'payment_method' => 'cash', 'advance_id' => $id, 'advance_amount' => 5000,
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(5000, (float) Advance::withoutGlobalScopes()->findOrFail($id)->remaining_amount, 0.01);
    }

    public function test_o_por_pagar_do_modal_conta_as_notas_de_credito(): void
    {
        $factura = $this->factura(10000);
        \App\Models\Invoicing\CreditNote::withoutEvents(fn () => \App\Models\Invoicing\CreditNote::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->comprador->id, 'invoice_id' => $factura->id,
            'credit_note_number' => 'NC ENSAIO/' . random_int(1000, 9999), 'issue_date' => now()->toDateString(),
            'subtotal' => 6000, 'total' => 6000, 'status' => 'issued', 'created_by' => $this->user->id,
        ]));

        $this->getJson(self::RAIZ . '/pagamentos/sale/' . $factura->id)->assertOk()->assertJsonPath('por_pagar', 4000);
    }
}
