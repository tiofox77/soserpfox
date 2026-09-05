<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Billing;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Gestão de Facturação («admin a pagar o plano»): o acordo também entra aqui.
 *
 * O ecrã de facturação do super-admin é a outra porta por onde se fecha um
 * plano à mão — e dava sempre 14 meses ao anual e o preço de tabela. Agora
 * grava o mesmo acordo que o modal «Alterar Plano»: oferta opcional, dias à
 * medida, preço por utilizador — pela MESMA regra (AcordoDeSubscricao).
 */
class FacturacaoAcordoTest extends TenantTestCase
{
    private function plano(float $mensal = 10000, float $anual = 100000, int $utilizadores = 10): Plan
    {
        return Plan::create([
            'name' => 'Plano '.uniqid(), 'slug' => 'plano-'.uniqid(),
            'price_monthly' => $mensal, 'price_yearly' => $anual, 'trial_days' => 0,
            'max_users' => $utilizadores, 'max_storage_mb' => 5000, 'is_active' => true,
        ]);
    }

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Empresa '.uniqid(), 'slug' => 'emp-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
    }

    private function ecra()
    {
        // O ecrã só abre ao dono da plataforma.
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user);

        return Livewire::test(Billing::class);
    }

    /** @test */
    public function o_resumo_segue_o_acordo(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano(10000, 100000, 10);

        $ecra = $this->ecra()
            ->set('tenant_id', $empresa->id)
            ->set('plan_id', $plano->id)
            ->set('billing_cycle', 'yearly');

        $this->assertSame(100000.0, $ecra->instance()->resumoDoAcordo['valor']);
        $this->assertTrue(now()->addMonths(14)->isSameDay($ecra->instance()->resumoDoAcordo['fim']));

        $ecra->set('com_oferta', false);
        $this->assertTrue(now()->addMonths(12)->isSameDay($ecra->instance()->resumoDoAcordo['fim']));

        $ecra->set('preco_por_utilizador', '1500')->set('utilizadores_cobrados', '4');
        $this->assertSame(6000.0, $ecra->instance()->resumoDoAcordo['valor']);
    }

    /** @test */
    public function marcar_como_pago_grava_o_acordo_na_subscricao(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano(10000, 100000, 10);

        $this->ecra()
            ->set('tenant_id', $empresa->id)
            ->set('plan_id', $plano->id)
            ->set('billing_cycle', 'yearly')
            ->set('com_oferta', false)
            ->set('preco_por_utilizador', '1500')
            ->set('utilizadores_cobrados', '4')
            ->set('marcarComoPago', true)
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $sub = Subscription::where('tenant_id', $empresa->id)->where('status', 'active')->first();

        $this->assertNotNull($sub, 'a subscrição activa não foi criada');
        $this->assertFalse((bool) $sub->com_oferta);
        $this->assertSame(12, (int) $sub->current_period_start->diffInMonths($sub->current_period_end));
        $this->assertSame(6000.0, (float) $sub->amount);
        $this->assertSame(1500.0, (float) $sub->preco_por_utilizador);
        $this->assertSame(4, (int) $sub->utilizadores_cobrados);
    }

    /** @test */
    public function dias_a_medida_ganham_ao_ciclo_tambem_aqui(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();

        $this->ecra()
            ->set('tenant_id', $empresa->id)
            ->set('plan_id', $plano->id)
            ->set('billing_cycle', 'yearly')
            ->set('dias_personalizados', '364')
            ->set('marcarComoPago', true)
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $sub = Subscription::where('tenant_id', $empresa->id)->where('status', 'active')->first();

        $this->assertSame(364, (int) $sub->dias_personalizados);
        $this->assertSame(364, (int) $sub->current_period_start->diffInDays($sub->current_period_end));
    }

    /** Editar traz o acordo gravado — não o «esquece». */
    public function test_editar_traz_o_acordo_gravado(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();
        $sub = app(\App\Services\Plataforma\TrocarDePlano::class)->aplicar($empresa, $plano, 'yearly', [
            'com_oferta' => false, 'preco_por_utilizador' => 2000, 'utilizadores' => 3,
        ]);

        $this->ecra()
            ->call('editSubscription', $sub->id)
            ->assertSet('com_oferta', false)
            ->assertSet('preco_por_utilizador', '2000')
            ->assertSet('utilizadores_cobrados', '3');
    }
}
