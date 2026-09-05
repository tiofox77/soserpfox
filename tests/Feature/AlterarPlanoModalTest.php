<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Tenants as EcraTenants;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\TrocarDePlano;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O modal «Alterar Plano» é dinâmico e grava o acordo que mostra.
 *
 * Duas queixas do dono (2026-09-02): o modal mostrava um plano actual
 * diferente do da lista, e não deixava fechar um acordo diferente da tabela
 * (sem oferta, N dias, preço por utilizador). O que se prende aqui:
 *
 *   · abrir o modal traz o acordo ACTUAL da empresa, tal como está;
 *   · o resumo recalcula ao mudar ciclo/dias/preço, no servidor, pela
 *     mesma regra que grava;
 *   · o que se grava é exactamente o que o resumo prometeu.
 */
class AlterarPlanoModalTest extends TenantTestCase
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
        $this->actingAs($this->user);

        return Livewire::test(EcraTenants::class);
    }

    /** @test */
    public function abrir_o_modal_traz_o_acordo_actual_da_empresa(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();
        app(TrocarDePlano::class)->aplicar($empresa, $plano, 'yearly', [
            'com_oferta' => false, 'preco_por_utilizador' => 1500, 'utilizadores' => 4,
        ]);

        $this->ecra()
            ->call('managePlan', $empresa->id)
            ->assertSet('showPlanModal', true)
            ->assertSet('selectedPlanId', $plano->id)
            ->assertSet('billingCycle', 'yearly')
            ->assertSet('comOferta', false)
            ->assertSet('precoPorUtilizador', '1500')
            ->assertSet('utilizadoresCobrados', '4')
            ->assertSee('sem oferta')
            ->assertSee('4 utilizador(es)');
    }

    /** @test */
    public function o_resumo_recalcula_com_o_que_se_escreve(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano(10000, 100000, 10);

        $ecra = $this->ecra()
            ->call('managePlan', $empresa->id)
            ->set('selectedPlanId', $plano->id)
            ->set('billingCycle', 'yearly');

        // Anual com oferta: 14 meses, preço de tabela. (Ao dia — o resumo é
        // calculado uns milissegundos antes do now() daqui.)
        $resumo = $ecra->instance()->resumoDoPlano;
        $this->assertSame(100000.0, $resumo['valor']);
        $this->assertTrue(now()->addMonths(14)->isSameDay($resumo['fim']));
        $this->assertTrue($resumo['oferta_aplicavel']);

        // Tira-se a oferta: 12 meses.
        $ecra->set('comOferta', false);
        $this->assertTrue(now()->addMonths(12)->isSameDay($ecra->instance()->resumoDoPlano['fim']));

        // Dias à medida ganham ao ciclo.
        $ecra->set('diasPersonalizados', '364');
        $resumo = $ecra->instance()->resumoDoPlano;
        $this->assertSame(364, $resumo['dias']);
        $this->assertFalse($resumo['oferta_aplicavel'], 'com dias à medida a oferta não se aplica');

        // Preço por utilizador: N × preço, N por omissão = os do plano.
        $ecra->set('precoPorUtilizador', '2000');
        $this->assertSame(20000.0, $ecra->instance()->resumoDoPlano['valor']);
        $ecra->set('utilizadoresCobrados', '3');
        $this->assertSame(6000.0, $ecra->instance()->resumoDoPlano['valor']);
    }

    /** @test */
    public function grava_exactamente_o_que_o_resumo_prometeu(): void
    {
        $empresa = $this->empresa();
        $antigo = $this->plano();
        $novo = $this->plano(12000, 120000, 20);
        app(TrocarDePlano::class)->aplicar($empresa, $antigo, 'monthly');

        $this->ecra()
            ->call('managePlan', $empresa->id)
            ->set('selectedPlanId', $novo->id)
            ->set('billingCycle', 'yearly')
            ->set('comOferta', false)
            ->set('precoPorUtilizador', '1500')
            ->set('utilizadoresCobrados', '6')
            ->call('updateTenantPlan')
            ->assertHasNoErrors()
            ->assertSet('showPlanModal', false);

        $sub = $empresa->fresh()->activeSubscription;

        $this->assertSame($novo->id, $sub->plan_id);
        $this->assertSame('yearly', $sub->billing_cycle);
        $this->assertFalse((bool) $sub->com_oferta);
        $this->assertSame(12, (int) $sub->current_period_start->diffInMonths($sub->current_period_end));
        $this->assertSame(9000.0, (float) $sub->amount);
        $this->assertSame(6, (int) $sub->utilizadores_cobrados);
    }

    /** @test */
    public function dias_a_medida_ficam_gravados(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();

        $this->ecra()
            ->call('managePlan', $empresa->id)
            ->set('selectedPlanId', $plano->id)
            ->set('billingCycle', 'yearly')
            ->set('diasPersonalizados', '364')
            ->call('updateTenantPlan')
            ->assertHasNoErrors();

        $sub = $empresa->fresh()->activeSubscription;

        $this->assertSame(364, (int) $sub->dias_personalizados);
        $this->assertSame(364, (int) $sub->current_period_start->diffInDays($sub->current_period_end));
    }

    /** @test */
    public function dias_absurdos_nao_passam_a_validacao(): void
    {
        $empresa = $this->empresa();
        $plano = $this->plano();

        $this->ecra()
            ->call('managePlan', $empresa->id)
            ->set('selectedPlanId', $plano->id)
            ->set('diasPersonalizados', '99999')
            ->call('updateTenantPlan')
            ->assertHasErrors(['diasPersonalizados']);
    }

    /**
     * A lista e o modal têm de concordar. Uma empresa com DUAS subscrições
     * vivas (herança de antes do TrocarDePlano cancelar tudo) mostrava uma
     * na lista e outra no modal — a relação não tinha ordem determinística.
     *
     * @test
     */
    public function com_duas_subscricoes_vivas_ganha_a_que_acaba_mais_tarde(): void
    {
        $empresa = $this->empresa();
        $curto = $this->plano();
        $longo = $this->plano();

        $empresa->subscriptions()->create([
            'plan_id' => $curto->id, 'billing_cycle' => 'monthly', 'amount' => 1, 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $empresa->subscriptions()->create([
            'plan_id' => $longo->id, 'billing_cycle' => 'yearly', 'amount' => 1, 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        // Dez leituras, sempre a mesma resposta.
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($longo->id, Tenant::with('activeSubscription')->find($empresa->id)->activeSubscription->plan_id);
        }
    }
}
