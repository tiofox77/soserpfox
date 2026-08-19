<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use Tests\TenantTestCase;

/**
 * O teste que o plano anuncia é o teste que o cliente recebe.
 */
class TrialAutomaticoTest extends TenantTestCase
{
    public function test_o_comando_liga_a_activacao_automatica_nos_planos_com_teste(): void
    {
        Plan::where('trial_days', '>', 0)->update(['auto_activate' => false]);

        $this->artisan('planos:trial-automatico')->assertSuccessful();

        $this->assertSame(
            0,
            Plan::where('trial_days', '>', 0)->where('auto_activate', false)->count(),
            'todo o plano com período de teste tem de activar sozinho'
        );
    }

    public function test_planos_sem_teste_nao_sao_tocados(): void
    {
        $semTeste = Plan::create([
            'name' => 'Sem Teste', 'slug' => 'sem-teste-' . uniqid(),
            'price_monthly' => 1000, 'trial_days' => 0,
            'auto_activate' => false, 'is_active' => true,
        ]);

        $this->artisan('planos:trial-automatico')->assertSuccessful();

        $this->assertFalse((bool) $semTeste->fresh()->auto_activate);
    }

    public function test_iniciar_trial_poe_a_empresa_em_teste_e_tira_o_pedido_da_fila(): void
    {
        $plano = Plan::where('trial_days', '>', 0)->first();

        // Como o registo deixava estas empresas: subscrição PENDENTE (não
        // activa) e pedido à espera de aprovação.
        $this->tenant->subscriptions()->update(['status' => 'cancelled']);
        $this->tenant->subscriptions()->create([
            'plan_id'       => $plano->id,
            'amount'        => $plano->getPrice('monthly'),
            'billing_cycle' => 'monthly',
            'status'        => 'pending',
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'plan_id' => $plano->id,
            'amount' => $plano->getPrice('monthly'),
            'billing_cycle' => 'monthly',
            'status' => 'pending',
        ]);

        $this->artisan('tenant:iniciar-trial', ['tenant' => $this->tenant->id])
            ->assertSuccessful();

        $sub = $this->tenant->subscriptions()->latest('id')->first();

        $this->assertSame('trial', $sub->status);
        $this->assertNotNull($sub->trial_ends_at);
        $this->assertEqualsWithDelta($plano->trial_days, now()->diffInDays($sub->trial_ends_at), 1);

        // O pedido sai da fila — mas SEM período pago (o observer não corre).
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertSame(
            1,
            $this->tenant->subscriptions()->whereIn('status', ['trial', 'active'])->count(),
            'não pode nascer uma segunda subscrição viva'
        );
    }

    public function test_um_pedido_com_comprovativo_nao_e_convertido_em_teste(): void
    {
        $plano = Plan::where('trial_days', '>', 0)->first();

        Order::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'plan_id' => $plano->id,
            'amount' => $plano->getPrice('monthly'),
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'payment_proof' => 'payment-proofs/x.pdf',
        ]);

        $this->artisan('tenant:iniciar-trial', ['tenant' => $this->tenant->id])
            ->assertFailed();
    }
}
