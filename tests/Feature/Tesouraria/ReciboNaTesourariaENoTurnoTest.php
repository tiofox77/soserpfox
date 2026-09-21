<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Client;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Services\Invoicing\RegistoDePagamento;
use App\Services\Treasury\TreasuryMovementService;
use Tests\TenantTestCase;

/**
 * O DINHEIRO DO RECIBO TEM DE APARECER (19/09/2026).
 *
 * Três buracos no mesmo caminho:
 *
 *  · o recibo emitido pelo ECRÃ dos recibos baixava a dívida da factura e não
 *    entrava na TESOURARIA (só o modal de pagamento lançava);
 *  · nenhum dos caminhos registava o recibo no TURNO — o `PosShift` conta
 *    movimentos `receipt`, mas nunca existia nenhum, e o fecho de caixa
 *    ignorava o dinheiro recebido por recibo;
 *  · o NUMERÁRIO ia para a caixa por omissão do método em vez da caixa do
 *    operador, e se a caixa indicada não estivesse aberta o movimento ficava
 *    sem caixa nenhuma — desaparecia do fecho.
 */
class ReciboNaTesourariaENoTurnoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/recibos';

    private Client $facturado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')
            ->comPermissoes('invoicing.receipts.view', 'invoicing.receipts.create');

        $this->facturado = $this->clienteEmpresa();

        InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'RC', 'name' => 'RC (teste)',
            'document_type' => 'receipt', 'agt_environment' => 'sandbox',
            'is_default' => true, 'is_active' => true,
        ]);
    }

    private function factura(float $total): SalesInvoice
    {
        $f = new SalesInvoice([
            'client_id' => $this->facturado->id,
            'invoice_number' => 'FT ENSAIO/' . random_int(100000, 999999),
            'invoice_date' => now(), 'invoice_type' => 'FT', 'status' => 'sent',
            'subtotal' => $total, 'total' => $total, 'paid_amount' => 0,
            'created_by' => $this->user->id,
        ]);
        $f->tenant_id = $this->tenant->id;
        $f->save();

        return $f;
    }

    private function caixa(array $troca = []): CashRegister
    {
        return CashRegister::create(array_merge([
            'tenant_id' => $this->tenant->id, 'name' => 'Caixa ' . uniqid(), 'code' => 'CX' . random_int(1000, 9999),
            // Toda a caixa tem dono (coluna NOT NULL): por omissão é de um colega,
            // para as que são do operador serem explicitamente dele.
            'user_id' => $this->colega()->id,
            'is_active' => true, 'is_default' => false, 'status' => 'open',
            'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ], $troca));
    }

    private ?\App\Models\User $outro = null;

    /** Um colega qualquer — as caixas que não são do operador têm de ser de alguém. */
    private function colega(): \App\Models\User
    {
        return $this->outro ??= \App\Models\User::create([
            'name' => 'Colega', 'email' => 'colega' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
    }

    /** O método «Dinheiro» da empresa (já vem semeado), com a caixa por omissão que se quiser. */
    private function metodoDinheiro(?int $caixaPadrao = null): PaymentMethod
    {
        return PaymentMethod::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CASH'],
            ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true, 'default_cash_register_id' => $caixaPadrao],
        );
    }
    private function turnoAberto(): PosShift
    {
        return PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);
    }

    private function movimentoDoRecibo(Receipt $r): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->where('related_type', Receipt::class)->where('related_id', $r->id)->first();
    }

    public function test_o_recibo_do_ecra_entra_na_tesouraria_com_a_data_dele(): void
    {
        $factura = $this->factura(10000);
        $ontem = now()->subDay()->toDateString();

        $this->postJson(self::RAIZ, [
            'type' => 'sale', 'client_id' => $this->facturado->id, 'invoice_id' => $factura->id,
            'payment_date' => $ontem, 'payment_method' => 'cash', 'amount_paid' => 4000,
        ])->assertCreated();

        $recibo = Receipt::latest('id')->firstOrFail();
        $movimento = $this->movimentoDoRecibo($recibo);

        $this->assertNotNull($movimento, 'o recibo tem de aparecer na tesouraria');
        $this->assertSame('income', $movimento->type);
        $this->assertEqualsWithDelta(4000, (float) $movimento->amount, 0.01);
        $this->assertSame($factura->id, (int) $movimento->invoice_id);
        // A data é a do RECIBO, não a de agora — senão os mapas não cruzam.
        $this->assertSame($ontem, $movimento->transaction_date->toDateString());
    }

    public function test_o_recibo_conta_no_turno_e_no_fecho_de_caixa(): void
    {
        $turno = $this->turnoAberto();
        $factura = $this->factura(10000);

        $this->postJson(self::RAIZ, [
            'type' => 'sale', 'client_id' => $this->facturado->id, 'invoice_id' => $factura->id,
            'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'amount_paid' => 2500,
        ])->assertCreated();

        $turno->refresh();
        $movimento = $turno->transactions()->where('type', 'receipt')->first();

        $this->assertNotNull($movimento, 'o recibo tem de entrar no turno');
        $this->assertEqualsWithDelta(2500, (float) $movimento->amount, 0.01);
        $this->assertSame(1, (int) $turno->total_receipts);
        // Em dinheiro, o recibo faz subir o esperado na gaveta.
        $this->assertEqualsWithDelta(2500, (float) $turno->cash_sales, 0.01);
    }

    public function test_o_recibo_do_modal_de_pagamento_lanca_uma_vez_so(): void
    {
        $this->turnoAberto();
        $factura = $this->factura(10000);

        app(RegistoDePagamento::class)->registar('sale', $factura->id, [
            'amount' => 3000, 'payment_method' => 'cash',
        ], $this->tenant->id, $this->user->id);

        $recibo = Receipt::latest('id')->firstOrFail();

        $this->assertSame(1, Transaction::withoutGlobalScopes()
            ->where('related_type', Receipt::class)->where('related_id', $recibo->id)->count(),
            'um recibo = um movimento de tesouraria');

        // Repetir o lançamento do mesmo recibo não cria dinheiro do nada.
        app(\App\Services\Invoicing\LancamentoDoRecibo::class)->lancar($recibo, [], $this->user->id);

        $this->assertSame(1, Transaction::withoutGlobalScopes()
            ->where('related_type', Receipt::class)->where('related_id', $recibo->id)->count());
    }

    public function test_um_recibo_pago_com_cartao_nao_entra_na_gaveta(): void
    {
        $banco = \App\Models\Treasury\Bank::firstOrCreate(
            ['code' => 'BFA'],
            ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true],
        );

        \App\Models\Treasury\Account::create([
            'tenant_id' => $this->tenant->id, 'bank_id' => $banco->id,
            'account_name' => 'Conta da casa', 'account_number' => (string) random_int(100000, 999999),
            'currency' => 'AOA', 'initial_balance' => 0, 'current_balance' => 0,
            'is_active' => true, 'is_default' => true,
        ]);
        $this->caixa(['is_default' => true, 'user_id' => $this->user->id]);

        $factura = $this->factura(10000);

        $this->postJson(self::RAIZ, [
            'type' => 'sale', 'client_id' => $this->facturado->id, 'invoice_id' => $factura->id,
            'payment_date' => now()->toDateString(), 'payment_method' => 'card', 'amount_paid' => 3000,
        ])->assertCreated();

        $movimento = $this->movimentoDoRecibo(Receipt::latest('id')->firstOrFail());

        $this->assertNotNull($movimento);
        // Faltava o `card` no MAPA_METODOS: caía no `cash` e o valor ia para a
        // gaveta, a inflar o numerário do fecho.
        $this->assertNull($movimento->cash_register_id, 'um pagamento com cartão não é numerário');
        $this->assertNotNull($movimento->account_id, 'o cartão entra numa conta bancária');
    }

    public function test_o_numerario_vai_para_a_caixa_do_operador_e_nao_para_a_do_metodo(): void
    {
        $daCasa = $this->caixa(['is_default' => true]);
        $doOperador = $this->caixa(['user_id' => $this->user->id]);

        $metodo = $this->metodoDinheiro($daCasa->id);

        $destino = app(TreasuryMovementService::class)
            ->destination($metodo, $this->tenant->id, null, null, $this->user->id);

        $this->assertSame($doOperador->id, $destino['cash_register_id'],
            'o numerário é da gaveta de quem vendeu, não da caixa por omissão do método');
    }

    public function test_a_caixa_escolhida_a_mao_continua_a_mandar(): void
    {
        $this->caixa(['is_default' => true]);
        $doOperador = $this->caixa(['user_id' => $this->user->id]);
        $escolhida = $this->caixa();

        $metodo = $this->metodoDinheiro();

        $destino = app(TreasuryMovementService::class)
            ->destination($metodo, $this->tenant->id, null, $escolhida->id, $this->user->id);

        $this->assertSame($escolhida->id, $destino['cash_register_id']);
        $this->assertNotSame($doOperador->id, $destino['cash_register_id']);
    }

    public function test_caixa_fechada_nao_faz_o_dinheiro_desaparecer_do_fecho(): void
    {
        $aberta = $this->caixa(['is_default' => true]);
        $fechada = $this->caixa(['status' => 'closed']);

        $metodo = $this->metodoDinheiro($fechada->id);

        $destino = app(TreasuryMovementService::class)
            ->destination($metodo, $this->tenant->id, null, null, $this->user->id);

        // Antes devolvia null e o movimento ficava sem caixa.
        $this->assertSame($aberta->id, $destino['cash_register_id']);
    }
}
