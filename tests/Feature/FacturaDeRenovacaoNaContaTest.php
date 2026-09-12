<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use Tests\TenantTestCase;

/**
 * A factura de renovação tem de aparecer ao cliente.
 *
 * Emitir a conta e não a mostrar era o mesmo que não a emitir: o separador de
 * facturação listava apenas PEDIDOS, e uma renovação não tem pedido nenhum —
 * tem factura. O cliente seria cortado no fim do período sem nunca ter visto a
 * conta em lado nenhum.
 */
class FacturaDeRenovacaoNaContaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/conta';

    protected function setUp(): void
    {
        parent::setUp();

        $papel = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole($papel);
    }

    /** Uma subscrição activa a acabar dentro de dias, já facturada. */
    private function comFacturaDeRenovacao(): \Carbon\Carbon
    {
        $plano = Plan::create([
            'name' => 'Empresarial', 'slug' => 'emp-'.uniqid(),
            'price_monthly' => 24900, 'price_yearly' => 249000,
            'max_users' => 20, 'is_active' => true,
        ]);

        $this->tenant->subscriptions()->update(['status' => 'cancelled']);

        $fim = now()->addDays(4);
        $this->tenant->subscriptions()->create([
            'plan_id' => $plano->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 24900,
            'current_period_start' => $fim->copy()->subMonth(),
            'current_period_end' => $fim,
            'ends_at' => $fim,
        ]);

        app(RenovacaoDeSubscricoes::class)->emitirFacturasAVencer();

        return $fim;
    }

    public function test_a_factura_por_pagar_aparece_na_conta(): void
    {
        $this->comFacturaDeRenovacao();

        $conta = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();

        $facturas = collect($conta->json('facturas'));

        $this->assertNotEmpty($facturas, 'a factura de renovação tem de chegar ao cliente');

        $porPagar = $facturas->where('estado', '!=', 'paid');

        $this->assertNotEmpty($porPagar);
        $this->assertEqualsWithDelta(24900, $porPagar->first()['total'], 0.01);
    }

    public function test_a_conta_diz_ate_quando_esta_pago(): void
    {
        $fim = $this->comFacturaDeRenovacao();

        $conta = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();

        $this->assertSame($fim->format('Y-m-d'), $conta->json('plano.termina_em'));
        // Quatro dias é menos de dez: o ecrã tem de marcar o período a acabar.
        $this->assertTrue($conta->json('plano.a_terminar'));
    }

    public function test_sem_facturas_por_pagar_nao_ha_nada_a_avisar(): void
    {
        $conta = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();

        $this->assertEmpty(
            collect($conta->json('facturas'))->where('estado', '!=', 'paid'),
        );
    }
}
