<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Billing;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
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
 */
class SuperAdminBillingTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O ecrã é do dono da plataforma, e cada acção verifica-o por si.
        $this->user->update(['is_super_admin' => true]);
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

        Livewire::test(Billing::class)
            ->call('editSubscription', $velha->id)
            ->assertSet('editingSubscriptionId', $velha->id)
            ->set('billing_cycle', 'quarterly')
            ->call('saveSubscription');

        // A subscrição viva ficou onde estava.
        $viva->refresh();
        $this->assertSame('active', $viva->status);
        $this->assertSame($business->id, $viva->plan_id);
        $this->assertSame('monthly', $viva->billing_cycle);
    }

    /** Fechar a modal esquece a linha — senão a "nova" gravava por cima dela. */
    public function test_fechar_a_modal_esquece_a_subscricao_que_estava_a_editar(): void
    {
        $plano = $this->plano('starter', 4900);
        $sub   = $this->subscricao($plano, 'active');

        Livewire::test(Billing::class)
            ->call('editSubscription', $sub->id)
            ->assertSet('editingSubscriptionId', $sub->id)
            ->call('closeSubscriptionModal')
            ->assertSet('editingSubscriptionId', null)
            ->call('createSubscription')
            ->assertSet('editingSubscriptionId', null);
    }

    // ==================== não cortar o acesso ====================

    /** Um plano por pagar não derruba o que está em vigor. */
    public function test_gravar_por_pagar_nao_despromove_a_subscricao_em_vigor(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->delete();

        $business   = $this->plano('business', 44900);
        $enterprise = $this->plano('enterprise', 89900);

        $emVigor = $this->subscricao($business, 'active', now()->addYear());

        Livewire::test(Billing::class)
            ->call('editSubscription', $emVigor->id)
            ->set('plan_id', $enterprise->id)
            ->set('marcarComoPago', false)
            ->call('saveSubscription');

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
        $antiga   = $this->subscricao($starter, 'active');

        Livewire::test(Billing::class)
            ->call('createSubscription')
            ->set('tenant_id', $this->tenant->id)
            ->set('plan_id', $business->id)
            ->set('billing_cycle', 'monthly')
            ->set('marcarComoPago', true)
            ->call('saveSubscription');

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

        Livewire::test(Billing::class)->call('cancelSubscription', $sub->id);

        $sub->refresh();
        $this->assertSame('active', $sub->status, 'O acesso tem de durar até ao fim do período.');
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_cancelar_um_periodo_ja_vencido_corta_na_hora(): void
    {
        $plano = $this->plano('business', 44900);
        $sub   = $this->subscricao($plano, 'active', now()->subDay());

        Livewire::test(Billing::class)->call('cancelSubscription', $sub->id);

        $this->assertSame('cancelled', $sub->refresh()->status);
    }

    /** Um teste em curso está a dar acesso — e é a prova da cortesia gasta. */
    public function test_nao_se_apaga_uma_subscricao_em_teste(): void
    {
        $plano = $this->plano('business', 44900, teste: 30);
        $sub   = $this->subscricao($plano, 'trial');

        Livewire::test(Billing::class)
            ->call('deleteSubscription', $sub->id)
            ->assertDispatched('error');

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

        Livewire::test(Billing::class)
            ->call('createSubscription')
            ->set('tenant_id', $this->tenant->id)
            ->set('plan_id', $business->id)
            ->set('billing_cycle', 'monthly')
            ->set('marcarComoPago', true)
            ->call('saveSubscription');

        $criada = Subscription::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $business->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($criada);
        $this->assertNull($criada->trial_ends_at, 'Uma subscrição paga não é um teste.');
    }

    // ==================== facturas ====================

    /** O total é somado no servidor: `readonly` não é validação. */
    public function test_o_total_da_factura_e_recalculado_e_nao_aceite_do_formulario(): void
    {
        Livewire::test(Billing::class)
            ->call('create')
            ->set('tenant_id', $this->tenant->id)
            ->set('description', 'Mensalidade')
            ->set('subtotal', 10000)
            ->set('tax', 1400)
            ->set('total', 1)              // o que um pedido forjado enviaria
            ->set('status', 'pending')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('11400.00', Invoice::latest('id')->first()->total);
    }

    /** Uma factura marcada como paga tem de ter data de pagamento. */
    public function test_factura_gravada_como_paga_fica_com_data_de_pagamento(): void
    {
        Livewire::test(Billing::class)
            ->call('create')
            ->set('tenant_id', $this->tenant->id)
            ->set('description', 'Mensalidade')
            ->set('subtotal', 10000)
            ->set('tax', 0)
            ->set('status', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Invoice::latest('id')->first()->paid_at);
    }

    /** Apagar o subtotal não pode rebentar o modal. */
    public function test_apagar_o_subtotal_nao_rebenta_o_modal(): void
    {
        Livewire::test(Billing::class)
            ->call('create')
            ->set('subtotal', '')
            ->set('tax', '')
            ->assertSet('total', 0.0);
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

        Livewire::test(Billing::class)->call('marcarFacturaComoPaga', $factura->id);

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

        Livewire::test(Billing::class)
            ->call('openRejectModal', $pedido->id)
            ->set('rejectionReason', '')
            ->call('rejectOrder')
            ->assertHasErrors('rejectionReason');

        $this->assertSame('pending', $pedido->refresh()->status);
    }

    public function test_o_motivo_da_recusa_fica_gravado(): void
    {
        $pedido = $this->pedidoPendente($this->plano('business', 44900));

        Livewire::test(Billing::class)
            ->call('openRejectModal', $pedido->id)
            ->set('rejectionReason', 'O comprovativo é de 24.900 Kz e o plano custa 44.900 Kz.')
            ->call('rejectOrder')
            ->assertHasNoErrors();

        $pedido->refresh();
        $this->assertSame('rejected', $pedido->status);
        $this->assertStringContainsString('24.900', $pedido->rejection_reason);
    }

    /** Um separador desactualizado não pode aprovar o que já foi decidido. */
    public function test_um_pedido_que_ja_nao_esta_pendente_nao_e_aprovado(): void
    {
        $pedido = $this->pedidoPendente($this->plano('business', 44900));
        $pedido->update(['status' => 'rejected']);

        Livewire::test(Billing::class)
            ->call('approveOrder', $pedido->id)
            ->assertDispatched('error');

        $this->assertSame('rejected', $pedido->refresh()->status);
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

        // O que interessa é o ecrã abrir. Antes, o primeiro
        // {{ $subscription->tenant->name }} levantava "Attempt to read
        // property on null" e o painel inteiro dava 500.
        Livewire::test(Billing::class)->assertOk();
    }

    /** Filtrar volta à primeira página. */
    public function test_mudar_de_filtro_volta_a_primeira_pagina(): void
    {
        // O 'page' do WithPagination não é uma propriedade pública que se
        // possa pôr à mão; usa-se o setPage do próprio trait.
        Livewire::test(Billing::class)
            ->call('setPage', 3)
            ->set('statusFilter', 'paid')
            ->assertSet('paginators.page', 1);
    }

    /** Trimestral e semestral não são "Mensal". */
    public function test_os_ciclos_tem_o_nome_certo(): void
    {
        $this->assertSame('Trimestral', Billing::nomeDoCiclo('quarterly'));
        $this->assertSame('Semestral', Billing::nomeDoCiclo('semiannual'));
        $this->assertSame('Anual', Billing::nomeDoCiclo('yearly'));
        $this->assertSame('Mensal', Billing::nomeDoCiclo('monthly'));
    }

    /** Quem não é dono da plataforma não passa, nem chamando o método. */
    public function test_quem_nao_e_dono_da_plataforma_e_recusado(): void
    {
        $this->user->update(['is_super_admin' => false]);

        $plano = $this->plano('business', 44900);
        $sub   = $this->subscricao($plano, 'active');

        Livewire::test(Billing::class)->call('cancelSubscription', $sub->id)->assertForbidden();

        $this->assertSame('active', $sub->refresh()->status);
    }
}
