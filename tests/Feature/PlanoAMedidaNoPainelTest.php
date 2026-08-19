<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Tenants as PainelTenants;
use App\Models\Module;
use App\Models\Plan;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Montar o plano à medida no painel: módulos com preço próprio, soma,
 * teste por módulo e contexto do que a empresa já tem.
 */
class PlanoAMedidaNoPainelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Module::firstOrCreate(['slug' => 'invoicing'], ['name' => 'Faturação', 'is_core' => false, 'default_price' => 4000]);
        Module::firstOrCreate(['slug' => 'treasury'], ['name' => 'Tesouraria', 'is_core' => false, 'default_price' => 0]);
        Module::firstOrCreate(['slug' => 'rh'], ['name' => 'Recursos Humanos', 'is_core' => false, 'default_price' => 6000]);
        Module::firstOrCreate(['slug' => 'salon'], ['name' => 'Salão', 'is_core' => false, 'default_price' => 3000]);

        $this->user->forceFill(['is_super_admin' => true])->save();
    }

    private function ecra()
    {
        return Livewire::actingAs($this->user)->test(PainelTenants::class)
            ->call('abrirPlanoAMedida', $this->tenant->id);
    }

    // ── Contexto: o que a empresa já tem ──────────────────────────

    public function test_mostra_os_modulos_que_a_empresa_ja_tem(): void
    {
        $mod = Module::where('slug', 'invoicing')->first();
        $this->tenant->modules()->syncWithoutDetaching([$mod->id => ['is_active' => true]]);

        $c = $this->ecra();

        $this->assertContains('invoicing', $c->get('medidaJaTem'));
        // E arranca já com eles escolhidos: o caso comum é acrescentar.
        $this->assertContains('invoicing', $c->get('medidaModulos'));
    }

    public function test_sugere_o_preco_base_de_cada_modulo(): void
    {
        $precos = $this->ecra()->get('medidaPrecos');

        $this->assertSame(4000.0, (float) $precos['invoicing']);
        $this->assertSame(6000.0, (float) $precos['rh']);
    }

    // ── Soma ───────────────────────────────────────────────────────

    public function test_a_mensalidade_e_a_soma_dos_modulos_escolhidos(): void
    {
        $c = $this->ecra()
            ->set('medidaModulos', ['invoicing', 'rh'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.rh', 6000);

        $this->assertSame(10000.0, $c->instance()->medidaTotalMensal);
    }

    public function test_tirar_um_modulo_baixa_a_soma(): void
    {
        $c = $this->ecra()
            ->set('medidaModulos', ['invoicing', 'rh'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.rh', 6000)
            ->set('medidaModulos', ['invoicing']);

        $this->assertSame(4000.0, $c->instance()->medidaTotalMensal);
    }

    public function test_o_anual_sugerido_e_doze_vezes_a_soma(): void
    {
        $c = $this->ecra()
            ->set('medidaModulos', ['invoicing'])
            ->set('medidaPrecos.invoicing', 5000);

        $this->assertSame(60000.0, $c->instance()->medidaAnualSugerido);
    }

    // ── Gravação ───────────────────────────────────────────────────

    public function test_cria_o_plano_com_o_preco_somado(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Soft')
            ->set('medidaModulos', ['invoicing', 'rh'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.rh', 6000)
            ->call('guardarPlanoAMedida')
            ->assertHasNoErrors()
            ->assertSet('showMedidaModal', false);

        $plano = Plan::where('name', 'Plano Soft')->first();

        $this->assertNotNull($plano);
        $this->assertSame(10000.0, (float) $plano->price_monthly);
        $this->assertFalse((bool) $plano->is_public);
    }

    public function test_grava_o_preco_de_cada_modulo_na_empresa(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Soft')
            ->set('medidaModulos', ['invoicing', 'rh'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.rh', 6000)
            ->call('guardarPlanoAMedida')
            ->assertHasNoErrors();

        $rh = $this->tenant->modules()->where('modules.slug', 'rh')->first();

        $this->assertSame(6000.0, (float) $rh->pivot->price);
    }

    // ── Teste por módulo ───────────────────────────────────────────

    public function test_um_modulo_com_dias_de_teste_fica_com_prazo(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Soft')
            ->set('medidaModulos', ['invoicing', 'salon'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.salon', 3000)
            ->set('medidaTestes.salon', 15)
            ->call('guardarPlanoAMedida')
            ->assertHasNoErrors();

        $salon = $this->tenant->modules()->where('modules.slug', 'salon')->first();
        $invo  = $this->tenant->modules()->where('modules.slug', 'invoicing')->first();

        $this->assertNotNull($salon->pivot->trial_ends_at, 'o módulo em teste tem de ter prazo');
        $this->assertNull($invo->pivot->trial_ends_at, 'um módulo sem teste não leva prazo');
    }

    public function test_o_modulo_em_teste_esta_disponivel_ate_ao_prazo(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Soft')
            ->set('medidaModulos', ['invoicing', 'salon'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.salon', 3000)
            ->set('medidaTestes.salon', 15)
            ->call('guardarPlanoAMedida')
            ->assertHasNoErrors();

        $this->assertTrue($this->tenant->fresh()->hasModule('salon'));
    }

    public function test_passado_o_prazo_so_esse_modulo_cai(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Soft')
            ->set('medidaModulos', ['invoicing', 'salon'])
            ->set('medidaPrecos.invoicing', 4000)
            ->set('medidaPrecos.salon', 3000)
            ->set('medidaTestes.salon', 15)
            ->call('guardarPlanoAMedida')
            ->assertHasNoErrors();

        // O prazo passou.
        $salon = Module::where('slug', 'salon')->first();
        $this->tenant->modules()->updateExistingPivot($salon->id, [
            'trial_ends_at' => now()->subDay(),
        ]);

        $tenant = $this->tenant->fresh();

        $this->assertFalse($tenant->hasModule('salon'), 'o módulo em teste caducou');
        $this->assertTrue($tenant->hasModule('invoicing'), 'o resto do plano continua');
    }

    // ── Guardas ────────────────────────────────────────────────────

    public function test_recusa_sem_modulos(): void
    {
        $this->ecra()
            ->set('medidaModulos', [])
            ->call('guardarPlanoAMedida')
            ->assertHasErrors('medidaModulos');
    }

    public function test_recusa_soma_zero(): void
    {
        $this->ecra()
            ->set('medidaNome', 'Plano Zero')
            ->set('medidaModulos', ['invoicing'])
            ->set('medidaPrecos.invoicing', 0)
            ->call('guardarPlanoAMedida')
            ->assertHasErrors('medidaPrecos');

        $this->assertNull(Plan::where('name', 'Plano Zero')->first());
    }
}
