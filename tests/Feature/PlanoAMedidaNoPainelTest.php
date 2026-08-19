<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Tenants as PainelTenants;
use App\Models\Module;
use App\Models\Plan;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Montar o plano à medida a partir do painel do super admin.
 */
class PlanoAMedidaNoPainelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['invoicing', 'treasury', 'rh'] as $slug) {
            Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_core' => false]);
        }

        // Só o dono da plataforma entra aqui.
        $this->user->forceFill(['is_super_admin' => true])->save();
    }

    private function ecra()
    {
        return Livewire::actingAs($this->user)->test(PainelTenants::class)
            ->call('abrirPlanoAMedida', $this->tenant->id);
    }

    public function test_o_ecra_abre_com_o_nome_da_empresa_sugerido(): void
    {
        $this->ecra()
            ->assertSet('showMedidaModal', true)
            ->assertSet('medidaNome', 'Plano ' . $this->tenant->name);
    }

    public function test_cria_e_atribui_o_plano(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Rei da Graça')
            ->set('medidaModulos', ['invoicing', 'rh'])
            ->set('medidaPrecoMensal', 30000)
            ->set('medidaUtilizadores', 15)
            ->call('guardarPlanoAMedida')
            ->assertHasNoErrors()
            ->assertSet('showMedidaModal', false);

        $plano = Plan::where('name', 'Plano Rei da Graça')->first();

        $this->assertNotNull($plano);
        $this->assertFalse((bool) $plano->is_public);
        $this->assertSame(
            $plano->id,
            $this->tenant->subscriptions()->whereIn('status', ['active', 'trial'])->latest('id')->first()->plan_id
        );
    }

    public function test_recusa_sem_modulos(): void
    {
        $this->ecra()
            ->set('medidaModulos', [])
            ->set('medidaPrecoMensal', 30000)
            ->call('guardarPlanoAMedida')
            ->assertHasErrors('medidaModulos');
    }

    public function test_recusa_mensalidade_zero(): void
    {
        $this->ecra()
            ->set('medidaModulos', ['invoicing'])
            ->set('medidaPrecoMensal', 0)
            ->call('guardarPlanoAMedida')
            ->assertHasErrors('medidaPrecoMensal');
    }

    public function test_o_anual_sugerido_e_doze_vezes_o_mensal(): void
    {
        $c = $this->ecra()->set('medidaPrecoMensal', 10000);

        $this->assertSame(120000.0, $c->instance()->medidaAnualSugerido);
    }
}
