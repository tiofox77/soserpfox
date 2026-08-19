<?php

namespace Tests\Feature;

use App\Livewire\MyAccount;
use App\Models\Plan;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A factura de renovação tem de aparecer ao cliente.
 *
 * Emitir a conta e não a mostrar era o mesmo que não a emitir: o separador de
 * facturação listava apenas PEDIDOS, e uma renovação não tem pedido nenhum —
 * tem factura. O cliente seria cortado no fim do período sem nunca ter visto
 * a conta em lado nenhum.
 */
class FacturaDeRenovacaoNaContaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $papel = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'Super Admin',
            'guard_name' => 'web',
            'tenant_id'  => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole($papel);
    }

    /** Uma subscrição activa a acabar dentro de dias, já facturada. */
    private function comFacturaDeRenovacao(): void
    {
        $plano = Plan::create([
            'name' => 'Empresarial', 'slug' => 'emp-' . uniqid(),
            'price_monthly' => 24900, 'price_yearly' => 249000,
            'max_users' => 20, 'is_active' => true,
        ]);

        $this->tenant->subscriptions()->update(['status' => 'cancelled']);

        $fim = now()->addDays(4);
        $this->tenant->subscriptions()->create([
            'plan_id'              => $plano->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'amount'               => 24900,
            'current_period_start' => $fim->copy()->subMonth(),
            'current_period_end'   => $fim,
            'ends_at'              => $fim,
        ]);

        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();
    }

    public function test_a_factura_por_pagar_aparece_no_separador_de_facturacao(): void
    {
        $this->comFacturaDeRenovacao();

        Livewire::actingAs($this->user)
            ->test(MyAccount::class)
            ->call('setActiveTab', 'billing')
            ->assertSee('Facturas da subscrição')
            ->assertSee('factura(s) por pagar')
            ->assertSee('24.900,00');
    }

    public function test_o_separador_do_plano_diz_ate_quando_esta_pago(): void
    {
        $this->comFacturaDeRenovacao();

        $fim = $this->tenant->subscriptions()
            ->where('status', 'active')->latest('id')->first()->current_period_end;

        Livewire::actingAs($this->user)
            ->test(MyAccount::class)
            ->call('setActiveTab', 'plan')
            ->assertSee('Válido até')
            ->assertSee($fim->format('d/m/Y'));
    }

    public function test_sem_facturas_por_pagar_nao_ha_aviso(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAccount::class)
            ->call('setActiveTab', 'billing')
            ->assertDontSee('factura(s) por pagar');
    }
}
