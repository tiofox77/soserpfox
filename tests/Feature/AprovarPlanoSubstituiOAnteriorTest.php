<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Activar um plano novo SUBSTITUI o anterior.
 *
 * Cancelava-se só a subscrição com status 'active'. Uma em 'trial' ficava de
 * pé ao lado da nova, e a empresa passava a ter duas ao mesmo tempo: dois
 * planos, dois limites, e a cobrança a olhar para a que calhasse.
 */
class AprovarPlanoSubstituiOAnteriorTest extends TestCase
{
    use DatabaseTransactions;

    private function plano(string $nome, float $preco): Plan
    {
        return Plan::create([
            'name' => $nome, 'slug' => strtolower($nome) . '-' . uniqid(),
            'price_monthly' => $preco, 'is_active' => true,
        ]);
    }

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Teste Subst ' . uniqid(), 'email' => uniqid() . '@teste.local',
            'nif' => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function aprovar(Tenant $t, Plan $p): void
    {
        $dono = \App\Models\User::create([
            'name' => 'Dono', 'email' => uniqid() . '@teste.local',
            'password' => bcrypt(uniqid()), 'tenant_id' => $t->id, 'is_active' => true,
        ]);

        $ordem = Order::create([
            'tenant_id' => $t->id, 'plan_id' => $p->id, 'user_id' => $dono->id,
            'status' => 'pending',
            'amount' => $p->price_monthly, 'billing_cycle' => 'monthly',
        ]);

        // Quem aprova fica gravado em approved_by; sem sessao, isso aponta
        // para um utilizador que nao existe e a chave estrangeira recusa.
        $this->actingAs($dono);

        $ordem->update(['status' => 'approved', 'approved_at' => now()]);
    }

    /** As que continuam vivas depois de tudo. */
    private function vivas(Tenant $t)
    {
        return Subscription::where('tenant_id', $t->id)
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->get();
    }

    public function test_uma_subscricao_em_TESTE_e_substituida(): void
    {
        $t = $this->empresa();
        $velho = $this->plano('Promocional', 0);
        $novo = $this->plano('Pago', 5900);

        $t->subscriptions()->create([
            'plan_id' => $velho->id, 'status' => 'trial', 'amount' => 0,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->aprovar($t, $novo);

        $vivas = $this->vivas($t);

        $this->assertCount(1, $vivas, 'ficaram duas subscrições vivas ao mesmo tempo');
        $this->assertSame($novo->id, (int) $vivas->first()->plan_id);
    }

    public function test_uma_subscricao_ACTIVA_continua_a_ser_substituida(): void
    {
        $t = $this->empresa();
        $velho = $this->plano('Antigo', 4900);
        $novo = $this->plano('Novo', 17900);

        $t->subscriptions()->create([
            'plan_id' => $velho->id, 'status' => 'active', 'amount' => 4900,
            'ends_at' => now()->addMonth(),
        ]);

        $this->aprovar($t, $novo);

        $vivas = $this->vivas($t);

        $this->assertCount(1, $vivas);
        $this->assertSame($novo->id, (int) $vivas->first()->plan_id);
    }

    public function test_o_plano_novo_fica_mesmo_activo(): void
    {
        $t = $this->empresa();
        $novo = $this->plano('Unico', 5900);

        $this->aprovar($t, $novo);

        $viva = $this->vivas($t)->first();

        $this->assertNotNull($viva);
        $this->assertSame('active', $viva->status);
        $this->assertNotNull($viva->ends_at, 'sem data de fim nunca mais se cobra');
    }
}
