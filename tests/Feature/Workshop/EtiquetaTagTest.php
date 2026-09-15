<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Tests\TenantTestCase;

/**
 * A ETIQUETA DA CHAVE COM TAG# E QR (15/09/2026, OF-20).
 */
class EtiquetaTagTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(?string $tag): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA' . random_int(1000, 9999) . 'RP', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'João Manuel', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active', 'tag_number' => $tag, 'work_order_ref' => '1050186']);

        return WorkOrder::create(['order_number' => 'OS-TAG-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    public function test_a_etiqueta_leva_o_tag_a_matricula_e_o_qr_da_folha_de_obra(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $o = $this->ordem('42');

        $r = $this->get("/workshop/work-orders/{$o->id}/tag")->assertOk();
        $r->assertSee('42')->assertSee($o->vehicle->plate)->assertSee($o->order_number)->assertSee('1050186')->assertSee('<svg', false);
        $this->assertStringNotContainsString('<?xml', $r->getContent());

        // A folha A4 leva oito; sem TAG# fica a caixa para escrever à mão.
        $a4 = $this->get('/workshop/work-orders/' . $this->ordem(null)->id . '/tag?formato=a4')->assertOk()->getContent();
        $this->assertSame(8, substr_count($a4, 'class="etiqueta"'));
        $this->assertStringContainsString('class="vazio"', $a4);
    }

    public function test_so_quem_ve_ordens_e_so_da_propria_empresa(): void
    {
        $o = $this->ordem('7');
        $this->get("/workshop/work-orders/{$o->id}/tag")->assertForbidden();

        $this->comPermissoes('workshop.work-orders.view');
        $outra = \App\Models\Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $o->forceFill(['tenant_id' => $outra->id])->saveQuietly();
        $this->get("/workshop/work-orders/{$o->id}/tag")->assertNotFound();
    }
}
