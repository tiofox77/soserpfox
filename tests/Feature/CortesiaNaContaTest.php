<?php

namespace Tests\Feature;

use App\Livewire\MyAccount;
use App\Models\Plan;
use App\Models\Subscription;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A regra da cortesia também tem de valer na área de conta.
 *
 * É por aqui que se muda de plano depois de a conta existir, e era aqui que
 * estava a porta larga: a verificação antiga olhava só para a bandeira
 * `is_promotional`, só para o MESMO plano e só na MESMA empresa. Bastava
 * saltar do FOX Friendly para os 30 dias de teste do Business, e desses para
 * os 30 do Enterprise.
 */
class CortesiaNaContaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sem papel de gestão, openUpgradeModal é travado logo por
        // guardCanManage() — e os testes passariam pela razão errada, a ver
        // um erro que não é o da cortesia.
        $papel = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'Super Admin',
            'guard_name' => 'web',
            'tenant_id'  => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole($papel);
    }

    private function planoGratuito(): Plan
    {
        return Plan::create([
            'name' => 'Amigo', 'slug' => 'amigo-' . uniqid(), 'description' => 'Grátis',
            'price_monthly' => 0, 'price_yearly' => 0, 'trial_days' => 180,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true,
            'is_promotional' => true, 'auto_activate' => true, 'order' => 1,
        ]);
    }

    private function planoPago(int $teste = 30): Plan
    {
        return Plan::create([
            'name' => 'Empresarial', 'slug' => 'empresarial-' . uniqid(), 'description' => 'Pago',
            'price_monthly' => 24900, 'price_yearly' => 249000, 'trial_days' => $teste,
            'max_users' => 20, 'max_companies' => 3, 'is_active' => true, 'order' => 2,
        ]);
    }

    /** Limpa a subscrição que a TenantTestCase cria, para partir do zero. */
    private function semHistorico(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->delete();
    }

    private function comSubscricao(Plan $plano, string $estado, bool $comTeste = false): void
    {
        $this->semHistorico();

        Subscription::create([
            'tenant_id'     => $this->tenant->id,
            'plan_id'       => $plano->id,
            'status'        => $estado,
            'amount'        => $plano->price_monthly,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => $comTeste ? now()->addDays($plano->trial_days) : null,
        ]);
    }

    public function test_o_gratuito_esta_disponivel_a_quem_nunca_o_teve(): void
    {
        $gratis = $this->planoGratuito();
        $this->semHistorico();

        Livewire::test(MyAccount::class)
            ->assertSet('direitoACortesia.jaTeveGratuito', false);

        $this->assertNull(
            \App\Services\Subscriptions\DireitoACortesia::daEmpresa($this->tenant, $this->user)
                ->motivoParaRecusar($gratis)
        );
    }

    public function test_o_gratuito_fecha_depois_de_usado(): void
    {
        $gratis = $this->planoGratuito();
        $this->comSubscricao($gratis, 'expired');

        Livewire::test(MyAccount::class)
            ->call('openUpgradeModal', $gratis->id)
            ->assertSet('showUpgradeModal', false)
            ->assertDispatched('error');
    }

    /** Já pagou: o gratuito deixa de ser uma saída. */
    public function test_quem_tem_plano_pago_nao_desce_para_o_gratuito(): void
    {
        $pago   = $this->planoPago();
        $gratis = $this->planoGratuito();
        $this->comSubscricao($pago, 'active');

        Livewire::test(MyAccount::class)
            ->call('openUpgradeModal', $gratis->id)
            ->assertSet('showUpgradeModal', false)
            ->assertDispatched('error');
    }

    /** Mudar para um plano pago continua a poder. */
    public function test_um_plano_pago_abre_normalmente(): void
    {
        $gratis = $this->planoGratuito();
        $pago   = $this->planoPago();
        $this->comSubscricao($gratis, 'expired');

        Livewire::test(MyAccount::class)
            ->call('openUpgradeModal', $pago->id)
            ->assertSet('showUpgradeModal', true)
            ->assertNotDispatched('error');
    }

    /**
     * O teste gasto num plano não se repete noutro.
     *
     * Sem cortesia por gastar, o pedido segue o caminho normal — fica
     * pendente à espera do comprovativo — em vez de se auto-aprovar.
     */
    public function test_o_teste_ja_gasto_nao_auto_ativa_outro_plano(): void
    {
        $primeiro = $this->planoPago();
        $this->comSubscricao($primeiro, 'trial', comTeste: true);

        $segundo = Plan::create([
            'name' => 'Promo', 'slug' => 'promo-' . uniqid(), 'description' => 'x',
            'price_monthly' => 9900, 'price_yearly' => 99000, 'trial_days' => 30,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true,
            'auto_activate' => true, 'order' => 3,
        ]);

        Livewire::test(MyAccount::class)
            ->call('openUpgradeModal', $segundo->id)
            ->assertSet('showUpgradeModal', true)
            ->set('upgradeBillingCycle', 'monthly')
            ->call('processUpgrade');

        $pedido = \App\Models\Order::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $segundo->id)
            ->latest()
            ->first();

        $this->assertNotNull($pedido, 'O pedido devia ter sido criado.');
        $this->assertSame('pending', $pedido->status, 'Sem cortesia por gastar, não se auto-aprova.');
    }

    /** Com a cortesia por gastar, o plano auto-activável arranca sozinho. */
    public function test_com_cortesia_por_gastar_o_plano_auto_ativavel_arranca(): void
    {
        $this->semHistorico();

        $promo = Plan::create([
            'name' => 'Promo', 'slug' => 'promo-' . uniqid(), 'description' => 'x',
            'price_monthly' => 9900, 'price_yearly' => 99000, 'trial_days' => 30,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true,
            'auto_activate' => true, 'order' => 3,
        ]);

        Livewire::test(MyAccount::class)
            ->call('openUpgradeModal', $promo->id)
            ->set('upgradeBillingCycle', 'monthly')
            ->call('processUpgrade');

        $pedido = \App\Models\Order::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $promo->id)
            ->latest()
            ->first();

        $this->assertNotNull($pedido);
        $this->assertSame('approved', $pedido->status);
    }
}
