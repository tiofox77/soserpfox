<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use Tests\TenantTestCase;

/**
 * A regra da cortesia também tem de valer na área de conta.
 *
 * É por aqui que se muda de plano depois de a conta existir, e era aqui que
 * estava a porta larga: a verificação antiga olhava só para a bandeira
 * `is_promotional`, só para o MESMO plano e só na MESMA empresa. Bastava saltar
 * do FOX Friendly para os 30 dias de teste do Business, e desses para os 30 do
 * Enterprise.
 *
 * O ecrã é hoje React: a recusa vem na lista de planos (para o ecrã a poder
 * explicar em vez de deixar um botão que não faz nada) e é verificada outra vez
 * ao contratar — o ecrã pode ser contornado, o pedido vem de fora.
 */
class CortesiaNaContaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/conta';

    protected function setUp(): void
    {
        parent::setUp();

        // Sem papel de gestão, o pedido é travado logo por falta de permissão —
        // e os testes passariam pela razão errada, a ver um erro que não é o da
        // cortesia.
        $papel = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole($papel);
    }

    private function planoGratuito(): Plan
    {
        return Plan::create([
            'name' => 'Amigo', 'slug' => 'amigo-'.uniqid(), 'description' => 'Grátis',
            'price_monthly' => 0, 'price_yearly' => 0, 'trial_days' => 180,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true,
            'is_promotional' => true, 'auto_activate' => true, 'order' => 1,
        ]);
    }

    private function planoPago(int $teste = 30): Plan
    {
        return Plan::create([
            'name' => 'Empresarial', 'slug' => 'empresarial-'.uniqid(), 'description' => 'Pago',
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
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plano->id,
            'status' => $estado,
            'amount' => $plano->price_monthly,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => $comTeste ? now()->addDays($plano->trial_days) : null,
        ]);
    }

    /** A recusa de um plano, tal como o ecrã a recebe — ou null se puder. */
    private function recusaDe(Plan $plano): ?string
    {
        $planos = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()->json('planos');

        return collect($planos)->firstWhere('id', $plano->id)['recusa'] ?? null;
    }

    private function contratar(Plan $plano, string $ciclo = 'monthly')
    {
        return $this->actingAs($this->user)->postJson(self::RAIZ.'/contratar', [
            'plan_id' => $plano->id, 'ciclo' => $ciclo,
        ]);
    }

    public function test_o_gratuito_esta_disponivel_a_quem_nunca_o_teve(): void
    {
        $gratis = $this->planoGratuito();
        $this->semHistorico();

        $this->assertNull($this->recusaDe($gratis), 'o ecrã não pode recusar o que ainda ninguém usou');

        $this->assertNull(
            \App\Services\Subscriptions\DireitoACortesia::daEmpresa($this->tenant, $this->user)
                ->motivoParaRecusar($gratis)
        );
    }

    public function test_o_gratuito_fecha_depois_de_usado(): void
    {
        $gratis = $this->planoGratuito();
        $this->comSubscricao($gratis, 'expired');

        // O ecrã explica porquê, em vez de deixar um botão que não faz nada.
        $this->assertNotNull($this->recusaDe($gratis));

        // E o servidor recusa na mesma a quem contorne o ecrã.
        $this->contratar($gratis)->assertStatus(422);
    }

    /** Já pagou: o gratuito deixa de ser uma saída. */
    public function test_quem_tem_plano_pago_nao_desce_para_o_gratuito(): void
    {
        $pago = $this->planoPago();
        $gratis = $this->planoGratuito();
        $this->comSubscricao($pago, 'active');

        $this->assertNotNull($this->recusaDe($gratis));
        $this->contratar($gratis)->assertStatus(422);
    }

    /** Mudar para um plano pago continua a poder. */
    public function test_um_plano_pago_abre_normalmente(): void
    {
        $gratis = $this->planoGratuito();
        $pago = $this->planoPago();
        $this->comSubscricao($gratis, 'expired');

        $this->assertNull($this->recusaDe($pago));
        $this->contratar($pago)->assertCreated();
    }

    /**
     * O teste gasto num plano não se repete noutro.
     *
     * Sem cortesia por gastar, o pedido segue o caminho normal — fica pendente
     * à espera do comprovativo — em vez de se auto-aprovar.
     */
    public function test_o_teste_ja_gasto_nao_auto_ativa_outro_plano(): void
    {
        $primeiro = $this->planoPago();
        $this->comSubscricao($primeiro, 'trial', comTeste: true);

        $segundo = Plan::create([
            'name' => 'Promo', 'slug' => 'promo-'.uniqid(), 'description' => 'x',
            'price_monthly' => 9900, 'price_yearly' => 99000, 'trial_days' => 30,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true,
            'auto_activate' => true, 'order' => 3,
        ]);

        $this->contratar($segundo)->assertCreated()->assertJsonPath('activado', false);

        $pedido = \App\Models\Order::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $segundo->id)->latest()->first();

        $this->assertNotNull($pedido, 'O pedido devia ter sido criado.');
        $this->assertSame('pending', $pedido->status, 'Sem cortesia por gastar, não se auto-aprova.');
    }

    /** Com a cortesia por gastar, o plano auto-activável arranca sozinho. */
    public function test_com_cortesia_por_gastar_o_plano_auto_ativavel_arranca(): void
    {
        $this->semHistorico();

        $promo = Plan::create([
            'name' => 'Promo', 'slug' => 'promo-'.uniqid(), 'description' => 'x',
            'price_monthly' => 9900, 'price_yearly' => 99000, 'trial_days' => 30,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true,
            'auto_activate' => true, 'order' => 3,
        ]);

        $this->contratar($promo)->assertCreated()->assertJsonPath('activado', true);

        $pedido = \App\Models\Order::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $promo->id)->latest()->first();

        $this->assertNotNull($pedido);
        $this->assertSame('approved', $pedido->status);
    }

    /**
     * O PLANO É DE QUEM GERE A CONTA.
     *
     * Antes, qualquer utilizador da empresa — um caixa, um vendedor — trocava
     * o plano da casa.
     */
    public function test_quem_nao_gere_a_conta_nao_muda_de_plano(): void
    {
        $this->semHistorico();
        $pago = $this->planoPago();

        $caixa = \App\Models\User::create([
            'name' => 'Caixa', 'email' => 'caixa-conta@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $caixa->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->actingAs($caixa)->postJson(self::RAIZ.'/contratar', [
            'plan_id' => $pago->id, 'ciclo' => 'monthly',
        ])->assertForbidden();

        $this->assertSame(0, \App\Models\Order::where('tenant_id', $this->tenant->id)->count());
    }
}
