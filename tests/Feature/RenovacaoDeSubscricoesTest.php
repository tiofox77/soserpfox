<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Tests\TenantTestCase;

/**
 * O ciclo de facturação fechado: emitir a conta do período seguinte, e só
 * estender a subscrição quando ela for paga.
 *
 * Antes disto faltava a peça do meio — facturava-se ao contratar, cortava-se o
 * acesso no fim do período, e no meio não saía conta nenhuma.
 */
class RenovacaoDeSubscricoesTest extends TenantTestCase
{
    private RenovacaoDeSubscricoes $renovacao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renovacao = app(RenovacaoDeSubscricoes::class);
    }

    private function plano(array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'name'          => 'Plano de teste',
            'slug'          => 'plano-teste-' . uniqid(),
            'price_monthly' => 25000,
            'price_yearly'  => 250000,
            'max_users'     => 5,
            'is_active'     => true,
        ], $extra));
    }

    /** Subscrição activa que acaba daqui a $dias. */
    private function subscricao(int $dias, array $extra = []): Subscription
    {
        $fim = now()->addDays($dias);

        return $this->tenant->subscriptions()->create(array_merge([
            'plan_id'              => $this->plano()->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'amount'               => 25000,
            'current_period_start' => $fim->copy()->subMonth(),
            'current_period_end'   => $fim,
            'ends_at'              => $fim,
        ], $extra));
    }

    public function test_emite_a_factura_do_periodo_seguinte_antes_do_fim(): void
    {
        $sub = $this->subscricao(3);

        $r = $this->renovacao->emitirFacturasAVencer();

        $this->assertSame(1, $r['emitidas']);

        $factura = Invoice::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($factura, 'tinha de sair a conta do período seguinte');
        $this->assertSame('pending', $factura->status);
        $this->assertSame(25000.0, (float) $factura->total);
    }

    public function test_a_factura_vence_no_dia_em_que_o_periodo_acaba(): void
    {
        $sub = $this->subscricao(5);

        $this->renovacao->emitirFacturasAVencer();

        $factura = Invoice::where('subscription_id', $sub->id)->first();

        // É até aí que há tempo de pagar sem perder o acesso.
        $this->assertSame(
            $sub->current_period_end->toDateString(),
            $factura->due_date->toDateString()
        );
    }

    public function test_nao_factura_duas_vezes_o_mesmo_periodo(): void
    {
        $this->subscricao(3);

        $this->renovacao->emitirFacturasAVencer();
        $this->renovacao->emitirFacturasAVencer();
        $r = $this->renovacao->emitirFacturasAVencer();

        $this->assertSame(0, $r['emitidas']);
        $this->assertSame(1, Invoice::where('tenant_id', $this->tenant->id)->count(),
            'correr à boleia do tráfego não pode encher o cliente de contas iguais');
    }

    public function test_nao_factura_o_que_ainda_esta_longe_do_fim(): void
    {
        $this->subscricao(40);

        $r = $this->renovacao->emitirFacturasAVencer();

        $this->assertSame(0, $r['emitidas']);
    }

    public function test_nao_factura_subscricao_cancelada(): void
    {
        $this->subscricao(3, ['cancelled_at' => now()]);

        $r = $this->renovacao->emitirFacturasAVencer();

        $this->assertSame(0, $r['emitidas'], 'quem cancelou não volta a ser cobrado');
    }

    public function test_nao_factura_quem_esta_em_teste(): void
    {
        $this->subscricao(3, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3)]);

        $r = $this->renovacao->emitirFacturasAVencer();

        $this->assertSame(0, $r['emitidas'], 'o teste não se cobra');
    }

    public function test_usa_o_valor_combinado_com_o_cliente_e_nao_o_da_tabela(): void
    {
        // Preço negociado abaixo da tabela: é o que vale.
        $this->subscricao(3, ['amount' => 9000]);

        $this->renovacao->emitirFacturasAVencer();

        $factura = Invoice::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(9000.0, (float) $factura->total);
    }

    public function test_o_ciclo_anual_leva_o_preco_anual(): void
    {
        $plano = $this->plano();
        $this->subscricao(3, [
            'plan_id'       => $plano->id,
            'billing_cycle' => 'yearly',
            'amount'        => 0,   // sem valor gravado, vale a tabela
        ]);

        $this->renovacao->emitirFacturasAVencer();

        $factura = Invoice::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(250000.0, (float) $factura->total);
    }

    public function test_so_ver_nao_grava_nada(): void
    {
        $this->subscricao(3);

        $r = $this->renovacao->emitirFacturasAVencer(8, true);

        $this->assertSame(1, $r['emitidas']);
        $this->assertSame(0, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    // ---- pagar é que dá acesso -------------------------------------------

    public function test_pagar_a_factura_estende_a_subscricao(): void
    {
        $sub = $this->subscricao(3);
        $fimAntigo = $sub->current_period_end->copy();

        $this->renovacao->emitirFacturasAVencer();
        $factura = Invoice::where('subscription_id', $sub->id)->first();

        $factura->markAsPaid('bank_transfer', 'REF-1');

        $sub->refresh();
        $this->assertTrue($sub->current_period_end->greaterThan($fimAntigo));
        // O período novo começa onde o antigo acabou: quem paga adiantado não
        // perde os dias que ainda tinha.
        $this->assertSame($fimAntigo->toDateString(), $sub->current_period_start->toDateString());
        $this->assertEqualsWithDelta(
            $fimAntigo->copy()->addMonth()->timestamp,
            $sub->current_period_end->timestamp,
            120
        );
    }

    public function test_emitir_a_factura_nao_chega_para_estender(): void
    {
        $sub = $this->subscricao(3);
        $fimAntigo = $sub->current_period_end->copy();

        $this->renovacao->emitirFacturasAVencer();

        $sub->refresh();
        $this->assertSame(
            $fimAntigo->toDateString(),
            $sub->current_period_end->toDateString(),
            'cobrar não pode dar acesso — só pagar'
        );
    }

    public function test_pagar_duas_vezes_nao_soma_dois_periodos(): void
    {
        $sub = $this->subscricao(3);
        $this->renovacao->emitirFacturasAVencer();
        $factura = Invoice::where('subscription_id', $sub->id)->first();

        $factura->markAsPaid();
        $sub->refresh();
        $fimDepoisDoPagamento = $sub->current_period_end->copy();

        // Segunda passagem, pelo serviço e pelo observer.
        $this->renovacao->aplicarPagamento($factura->fresh());
        $factura->fresh()->update(['payment_reference' => 'outra']);

        $sub->refresh();
        $this->assertSame(
            $fimDepoisDoPagamento->toDateString(),
            $sub->current_period_end->toDateString()
        );
    }

    public function test_a_factura_inicial_da_subscricao_nao_estende_nada(): void
    {
        // Como a que sai ao contratar: o período já está aberto e vence-se
        // daqui a oito dias, não no fim do período.
        $sub = $this->subscricao(25);
        $fim = $sub->current_period_end->copy();

        $factura = Invoice::create([
            'tenant_id'       => $this->tenant->id,
            'subscription_id' => $sub->id,
            'invoice_number'  => Invoice::generateInvoiceNumber(),
            'description'     => 'Subscrição inicial',
            'invoice_date'    => now(),
            'due_date'        => now()->addDays(8),
            'subtotal'        => 25000,
            'tax'             => 0,
            'total'           => 25000,
            'status'          => 'pending',
        ]);

        $factura->markAsPaid();

        $sub->refresh();
        $this->assertSame($fim->toDateString(), $sub->current_period_end->toDateString());
    }

    public function test_o_pagamento_reactiva_uma_subscricao_que_acabou_de_expirar(): void
    {
        $sub = $this->subscricao(1);
        $this->renovacao->emitirFacturasAVencer();
        $factura = Invoice::where('subscription_id', $sub->id)->first();

        // Passou o fim do período sem pagar: o expire cortou o acesso.
        $sub->update(['status' => 'expired']);

        $factura->markAsPaid();

        $sub->refresh();
        $this->assertSame('active', $sub->status, 'pagar devolve o acesso');
        $this->assertTrue($sub->current_period_end->isFuture());
    }

    public function test_o_comando_corre_e_emite(): void
    {
        $this->subscricao(3);

        $this->artisan('subscriptions:renovar')->assertSuccessful();

        $this->assertSame(1, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_o_comando_em_so_ver_nao_grava(): void
    {
        $this->subscricao(3);

        $this->artisan('subscriptions:renovar --so-ver')->assertSuccessful();

        $this->assertSame(0, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    // ---- o interruptor ---------------------------------------------------

    public function test_desligado_o_trafego_nao_emite_nada(): void
    {
        config(['billing.renovacao_automatica' => false]);
        $this->subscricao(3);

        $this->correrOMiddleware();

        $this->assertSame(0, Invoice::where('tenant_id', $this->tenant->id)->count(),
            'com o interruptor desligado ninguém emite facturas sozinho');
    }

    public function test_ligado_o_trafego_emite(): void
    {
        config(['billing.renovacao_automatica' => true]);
        $this->subscricao(3);

        $this->correrOMiddleware();

        $this->assertSame(1, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_o_trafego_nao_emite_a_utilizadores_por_autenticar(): void
    {
        config(['billing.renovacao_automatica' => true]);
        $this->subscricao(3);

        $this->correrOMiddleware(autenticado: false);

        $this->assertSame(0, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    /** Simula o fim de um pedido web, que é onde a emissão pega boleia. */
    private function correrOMiddleware(bool $autenticado = true): void
    {
        \Illuminate\Support\Facades\Cache::forget('plataforma:facturar-renovacoes');

        if ($autenticado) {
            $this->actingAs($this->user);
        } else {
            auth()->logout();
        }

        app(\App\Http\Middleware\FacturarRenovacoes::class)->terminate(
            \Illuminate\Http\Request::create('/dashboard', 'GET'),
            new \Illuminate\Http\Response()
        );
    }
}
