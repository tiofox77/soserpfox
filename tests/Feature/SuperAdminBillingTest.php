<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\CicloDeFacturacao;
use Tests\TenantTestCase;

/**
 * Facturação da plataforma — o ecrã onde o dono do SaaS cobra aos clientes.
 *
 * Uma revisão em sete frentes com verificação adversarial deu 41 defeitos
 * confirmados. Os piores cortavam o acesso a empresas inteiras:
 *
 *   · abrir uma subscrição antiga para lhe corrigir o ciclo e gravar mexia na
 *     subscrição VIVA da mesma empresa — o id da linha clicada era deitado
 *     fora e o alvo voltava a ser adivinhado por empresa;
 *   · desligar "Marcar como PAGO" — que é exactamente o que a caixa promete
 *     para quem vai transferir amanhã — punha a subscrição em vigor a
 *     'pending', e o CheckSubscription só aceita 'active' e 'trial': a
 *     empresa ficava fora do ERP no pedido seguinte, com o ano já pago
 *     apagado do registo;
 *   · uma empresa apagada deixava ->tenant a null e o ecrã INTEIRO rebentava
 *     com 500, no primeiro {{ $subscription->tenant->name }}.
 *
 * O ecrã passou a React: as mesmas regras provam-se agora pela API em
 * `/api/v1/plataforma/react/facturacao`. O que era estado das janelas do
 * componente (fechar e abrir limpas) vive no browser.
 */
class SuperAdminBillingTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react/facturacao';

    protected function setUp(): void
    {
        parent::setUp();

        // O ecrã é do dono da plataforma.
        $this->user->update(['is_super_admin' => true]);
        $this->actingAs($this->user->fresh());
    }

    private function plano(string $slug, float $preco, int $teste = 0): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid(),
            'description' => 'x', 'price_monthly' => $preco, 'price_yearly' => $preco * 10,
            'trial_days' => $teste, 'max_users' => 10, 'max_companies' => 3,
            'max_storage_mb' => 1024, 'is_active' => true, 'order' => 1,
        ]);
    }

    private function subscricao(Plan $plano, string $estado, ?\DateTimeInterface $fim = null): Subscription
    {
        return Subscription::create([
            'tenant_id'            => $this->tenant->id,
            'plan_id'              => $plano->id,
            'status'               => $estado,
            'billing_cycle'        => 'monthly',
            'amount'               => $plano->price_monthly,
            'current_period_start' => now()->subMonth(),
            'current_period_end'   => $fim ?? now()->addYear(),
        ]);
    }

    // ==================== o alvo da edição ====================

    /** Editar a linha que se abriu, e não outra qualquer. */
    public function test_editar_uma_subscricao_nao_mexe_noutra_da_mesma_empresa(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->delete();

        $starter  = $this->plano('starter', 4900);
        $business = $this->plano('business', 44900);

        $velha = $this->subscricao($starter, 'cancelled');
        $viva  = $this->subscricao($business, 'active', now()->addYear());

        $this->putJson(self::API . "/subscricoes/{$velha->id}", [
            'plan_id' => $starter->id, 'billing_cycle' => 'quarterly', 'pago' => false,
        ])->assertOk();

        // A subscrição viva ficou onde estava.
        $viva->refresh();
        $this->assertSame('active', $viva->status);
        $this->assertSame($business->id, $viva->plan_id);
        $this->assertSame('monthly', $viva->billing_cycle);
    }

    /** A editar, a empresa está fechada — mandar outra no pedido não muda o dono. */
    public function test_a_edicao_nao_deixa_trocar_de_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $plano = $this->plano('business', 44900);
        $sub   = $this->subscricao($plano, 'cancelled');

        $this->putJson(self::API . "/subscricoes/{$sub->id}", [
            'tenant_id' => $outra->id, 'plan_id' => $plano->id, 'billing_cycle' => 'monthly', 'pago' => false,
        ])->assertOk();

        $this->assertSame($this->tenant->id, $sub->refresh()->tenant_id);
        $this->assertSame(0, Subscription::where('tenant_id', $outra->id)->count());
    }

    // ==================== não cortar o acesso ====================

    /** Um plano por pagar não derruba o que está em vigor. */
    public function test_gravar_por_pagar_nao_despromove_a_subscricao_em_vigor(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->delete();

        $business   = $this->plano('business', 44900);
        $enterprise = $this->plano('enterprise', 89900);

        $emVigor = $this->subscricao($business, 'active', now()->addYear());

        $this->putJson(self::API . "/subscricoes/{$emVigor->id}", [
            'plan_id' => $enterprise->id, 'billing_cycle' => 'monthly', 'pago' => false,
        ])->assertOk();

        // Continua activa, no plano que está pago.
        $emVigor->refresh();
        $this->assertSame('active', $emVigor->status, 'A subscrição em vigor não pode cair para pending.');
        $this->assertSame($business->id, $emVigor->plan_id);

        // E ficou uma pendente ao lado, à espera do pagamento.
        $this->assertTrue(
            Subscription::where('tenant_id', $this->tenant->id)
                ->where('plan_id', $enterprise->id)
                ->where('status', 'pending')
                ->exists()
        );
    }

    /** Duas subscrições a dar acesso ao mesmo tempo confundem meio sistema. */
    public function test_gravar_como_paga_cancela_as_outras_em_vigor(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->delete();

        $starter  = $this->plano('starter', 4900);
        $business = $this->plano('business', 44900);
        $this->subscricao($starter, 'active');

        $this->postJson(self::API . '/subscricoes', [
            'tenant_id' => $this->tenant->id, 'plan_id' => $business->id,
            'billing_cycle' => 'monthly', 'pago' => true,
        ])->assertCreated();

        $emVigor = Subscription::where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->count();

        $this->assertSame(1, $emVigor, 'Só pode ficar uma subscrição a dar acesso.');
    }

    /** Cancelar cumpre o que a confirmação promete. */
    public function test_cancelar_mantem_o_acesso_ate_ao_fim_do_periodo_pago(): void
    {
        $plano = $this->plano('business', 44900);
        $sub   = $this->subscricao($plano, 'active', now()->addMonths(6));

        $this->postJson(self::API . "/subscricoes/{$sub->id}/cancelar")->assertOk();

        $sub->refresh();
        $this->assertSame('active', $sub->status, 'O acesso tem de durar até ao fim do período.');
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_cancelar_um_periodo_ja_vencido_corta_na_hora(): void
    {
        $plano = $this->plano('business', 44900);
        $sub   = $this->subscricao($plano, 'active', now()->subDay());

        $this->postJson(self::API . "/subscricoes/{$sub->id}/cancelar")->assertOk();

        $this->assertSame('cancelled', $sub->refresh()->status);
    }

    /** Um teste em curso está a dar acesso — e é a prova da cortesia gasta. */
    public function test_nao_se_apaga_uma_subscricao_em_teste(): void
    {
        $plano = $this->plano('business', 44900, teste: 30);
        $sub   = $this->subscricao($plano, 'trial');

        $this->deleteJson(self::API . "/subscricoes/{$sub->id}")->assertStatus(422);

        $this->assertNotNull(Subscription::find($sub->id));
    }

    // ==================== a cortesia ====================

    /**
     * Vender um plano pago não pode gastar o período de teste do cliente.
     *
     * Gravava-se trial_ends_at sempre que o plano tivesse trial_days — e todos
     * os planos pagos têm. O DireitoACortesia conta trial_ends_at preenchido
     * como teste já usado: o cliente ficava sem direito ao teste e sem direito
     * ao plano gratuito, para sempre, por ter comprado.
     */
    public function test_uma_subscricao_paga_nao_queima_a_cortesia_do_cliente(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->delete();

        $business = $this->plano('business', 44900, teste: 30);

        $this->postJson(self::API . '/subscricoes', [
            'tenant_id' => $this->tenant->id, 'plan_id' => $business->id,
            'billing_cycle' => 'monthly', 'pago' => true,
        ])->assertCreated();

        $criada = Subscription::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $business->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($criada);
        $this->assertNull($criada->trial_ends_at, 'Uma subscrição paga não é um teste.');
    }

    // ==================== facturas ====================

    private function factura(array $troca = []): array
    {
        return array_merge([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-' . uniqid(),
            'description' => 'Mensalidade',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 10000,
            'tax' => 0,
            'status' => 'pending',
        ], $troca);
    }

    /** O total é somado no servidor: `readonly` não é validação. */
    public function test_o_total_da_factura_e_recalculado_e_nao_aceite_do_formulario(): void
    {
        $this->postJson(self::API . '/facturas', $this->factura([
            'subtotal' => 10000, 'tax' => 1400, 'total' => 1,   // o que um pedido forjado enviaria
        ]))->assertCreated();

        $this->assertSame('11400.00', Invoice::latest('id')->first()->total);
    }

    /** Uma factura marcada como paga tem de ter data de pagamento. */
    public function test_factura_gravada_como_paga_fica_com_data_de_pagamento(): void
    {
        $this->postJson(self::API . '/facturas', $this->factura(['status' => 'paid']))->assertCreated();

        $this->assertNotNull(Invoice::latest('id')->first()->paid_at);
    }

    /** Uma factura nova já vem numerada e com as datas de hoje e a trinta dias. */
    public function test_uma_factura_nova_vem_numerada(): void
    {
        $ficha = $this->getJson(self::API . '/facturas/nova')->assertOk()->json('ficha');

        $this->assertNotEmpty($ficha['invoice_number']);
        $this->assertSame(now()->toDateString(), $ficha['invoice_date']);
        $this->assertSame(now()->addDays(30)->toDateString(), $ficha['due_date']);
    }

    /** O número de uma factura é único. */
    public function test_o_numero_da_factura_e_unico(): void
    {
        $dados = $this->factura();

        $this->postJson(self::API . '/facturas', $dados)->assertCreated();
        $this->postJson(self::API . '/facturas', $dados)->assertStatus(422)->assertJsonValidationErrors('invoice_number');
    }

    /** "Marcar Paga" não pode apagar a referência do pagamento. */
    public function test_marcar_como_paga_nao_apaga_a_referencia_do_pagamento(): void
    {
        $factura = Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'invoice_number'    => 'INV-TESTE-' . uniqid(),
            'description'       => 'x',
            'invoice_date'      => now(),
            'due_date'          => now(),
            'subtotal'          => 1000, 'tax' => 0, 'total' => 1000,
            'status'            => 'pending',
            'payment_method'    => 'multicaixa',
            'payment_reference' => 'TRF-9911',
        ]);

        $this->postJson(self::API . "/facturas/{$factura->id}/pagar")->assertOk();

        $factura->refresh();
        $this->assertSame('paid', $factura->status);
        $this->assertSame('TRF-9911', $factura->payment_reference);
        $this->assertSame('multicaixa', $factura->payment_method);
    }

    // ==================== pedidos ====================

    private function pedidoPendente(Plan $plano): Order
    {
        return Order::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->user->id,
            'plan_id'   => $plano->id,
            'amount'    => $plano->price_monthly,
            'billing_cycle' => 'monthly',
            'status'    => 'pending',
            'payment_method' => 'bank_transfer',
        ]);
    }

    /** Recusar sem motivo não passa — é o que o cliente vai ler. */
    public function test_recusar_um_pedido_exige_motivo(): void
    {
        $pedido = $this->pedidoPendente($this->plano('business', 44900));

        $this->postJson(self::API . "/pedidos/{$pedido->id}/recusar", ['motivo' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->assertSame('pending', $pedido->refresh()->status);
    }

    public function test_o_motivo_da_recusa_fica_gravado(): void
    {
        $pedido = $this->pedidoPendente($this->plano('business', 44900));

        $this->postJson(self::API . "/pedidos/{$pedido->id}/recusar", [
            'motivo' => 'O comprovativo é de 24.900 Kz e o plano custa 44.900 Kz.',
        ])->assertOk();

        $pedido->refresh();
        $this->assertSame('rejected', $pedido->status);
        $this->assertStringContainsString('24.900', $pedido->rejection_reason);
    }

    /** Um separador desactualizado não pode aprovar o que já foi decidido. */
    public function test_um_pedido_que_ja_nao_esta_pendente_nao_e_aprovado(): void
    {
        $pedido = $this->pedidoPendente($this->plano('business', 44900));
        $pedido->update(['status' => 'rejected']);

        $this->postJson(self::API . "/pedidos/{$pedido->id}/aprovar")->assertStatus(422);

        $this->assertSame('rejected', $pedido->refresh()->status);
    }

    /** Sem comprovativo, o ecrã diz que não há — silêncio não é «ainda não vi». */
    public function test_o_pedido_diz_quando_nao_tem_comprovativo(): void
    {
        $pedido = $this->pedidoPendente($this->plano('business', 44900));

        $linha = collect($this->getJson(self::API)->assertOk()->json('pedidos'))->firstWhere('id', $pedido->id);

        $this->assertNull($linha['comprovativo']);
    }

    // ==================== o ecrã aguenta-se ====================

    /** Uma empresa apagada não pode derrubar o painel inteiro. */
    public function test_o_ecra_abre_com_uma_empresa_apagada(): void
    {
        $outra = Tenant::create([
            'name' => 'Apagada', 'slug' => 'apagada-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'a' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $plano = $this->plano('starter', 4900);

        Subscription::create([
            'tenant_id' => $outra->id, 'plan_id' => $plano->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'amount' => 4900,
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        Order::create([
            'tenant_id' => $outra->id, 'user_id' => $this->user->id, 'plan_id' => $plano->id,
            'amount' => 4900, 'billing_cycle' => 'monthly', 'status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);

        $outra->delete();

        // O que interessa é abrir. Antes, o primeiro {{ $subscription->tenant->name }}
        // levantava "Attempt to read property on null" e o painel inteiro dava 500.
        $this->getJson(self::API)->assertOk();
        $linha = collect($this->getJson(self::API . '/subscricoes?procura=Apagada')->assertOk()->json('subscricoes'))->first();

        $this->assertTrue($linha['empresa_apagada']);
    }

    /** Um estado inventado no filtro é recusado, e não devolve tudo como se não houvesse filtro. */
    public function test_um_estado_inventado_e_recusado(): void
    {
        $this->getJson(self::API . '/facturas?estado=inventado')->assertStatus(422);
        $this->getJson(self::API . '/subscricoes?estado=inventado')->assertStatus(422);
    }

    /** Trimestral e semestral não são "Mensal". */
    public function test_os_ciclos_tem_o_nome_certo(): void
    {
        $this->assertSame('Trimestral', CicloDeFacturacao::nome('quarterly'));
        $this->assertSame('Semestral', CicloDeFacturacao::nome('semiannual'));
        $this->assertSame('Anual', CicloDeFacturacao::nome('yearly'));
        $this->assertSame('Mensal', CicloDeFacturacao::nome('monthly'));
    }

    /** Quem não é dono da plataforma não passa. */
    public function test_quem_nao_e_dono_da_plataforma_e_recusado(): void
    {
        $this->user->update(['is_super_admin' => false]);
        $this->actingAs($this->user->fresh());

        $plano = $this->plano('business', 44900);
        $sub   = $this->subscricao($plano, 'active');

        $this->postJson(self::API . "/subscricoes/{$sub->id}/cancelar")->assertForbidden();

        $this->assertSame('active', $sub->refresh()->status);
    }
}
