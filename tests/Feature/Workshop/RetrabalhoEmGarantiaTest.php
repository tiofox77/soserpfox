<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * O RETRABALHO EM GARANTIA (15/09/2026, OF-19).
 */
class RetrabalhoEmGarantiaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function original(array $mais = []): WorkOrder
    {
        $m = Mechanic::create(['tenant_id' => $this->tenant->id, 'name' => 'Nelo', 'phone' => '923222' . random_int(100, 999), 'level' => 'pleno', 'hourly_rate' => 1500, 'is_active' => true]);
        $v = Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'GA', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Dono', 'brand' => 'Kia', 'model' => 'Rio', 'status' => 'active', 'mileage' => 70000, 'client_id' => $this->cliente->id]);
        $o = WorkOrder::create($mais + ['order_number' => 'OS-GA-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'mechanic_id' => $m->id, 'received_at' => now()->subDays(10), 'completed_at' => now()->subDays(9), 'problem_description' => 'Embraiagem', 'status' => 'delivered', 'priority' => 'normal', 'warranty_days' => 90, 'warranty_expires' => now()->addDays(80)]);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Embraiagem', 'quantity' => 1, 'unit_price' => 60000, 'hours' => 3]);

        return $o->fresh();
    }

    public function test_abre_a_garantia_ligada_a_original_e_nao_se_factura(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.work-orders.edit', 'invoicing.sales.invoices.create');
        $o = $this->original();

        $this->postJson(self::API . "/ordens/{$o->id}/garantia", ['causa' => 'peca'])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $r = $this->postJson(self::API . "/ordens/{$o->id}/garantia", ['causa' => 'mao_de_obra', 'motivo' => 'A embraiagem volta a patinar'])->assertCreated();

        $g = WorkOrder::findOrFail($r->json('ordem_id'));
        $this->assertSame([$o->id, 'mao_de_obra', $o->mechanic_id, $o->vehicle_id, 'high', 'pending'], [$g->warranty_of_id, $g->warranty_cause, $g->mechanic_id, $g->vehicle_id, $g->priority, $g->status]);
        $this->assertStringContainsString($o->order_number, $g->problem_description);

        $fichaOriginal = $this->getJson(self::API . "/ordens/{$o->id}")->json('data.garantia_ficha');
        $this->assertSame($g->order_number, $fichaOriginal['retrabalhos'][0]['numero']);
        $fichaGarantia = $this->getJson(self::API . "/ordens/{$g->id}")->json('data.garantia_ficha');
        $this->assertTrue($fichaGarantia['e_garantia']);
        $this->assertSame($o->order_number, $fichaGarantia['de_ordem']['numero']);
        $this->assertFalse($fichaGarantia['pode_abrir']);

        // Não se factura e não se abre garantia de uma garantia.
        WorkOrderItem::create(['work_order_id' => $g->id, 'type' => 'service', 'name' => 'Afinar', 'quantity' => 1, 'unit_price' => 20000, 'hours' => 1]);
        $g->update(['status' => 'completed', 'completed_at' => now()]);
        $this->postJson(self::API . "/ordens/{$g->id}/facturar")->assertStatus(422)->assertJsonFragment(['message' => 'Ordem de garantia: não se factura ao cliente.']);
        $this->postJson(self::API . "/ordens/{$g->id}/garantia", ['causa' => 'outro', 'motivo' => 'x'])->assertStatus(422);
        $this->assertFalse(collect(\App\Services\Workshop\FacturacaoDeFrotas::ordens($this->tenant->id, $this->cliente->id, null, null))->firstWhere('id', $g->id)['pode']);
    }

    public function test_fora_do_prazo_so_com_confirmacao_e_os_indicadores_contam_a_parte(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.reports.view');
        $o = $this->original(['warranty_expires' => now()->subDay()]);

        $this->postJson(self::API . "/ordens/{$o->id}/garantia", ['causa' => 'peca', 'motivo' => 'Ruído'])->assertStatus(422);
        $id = $this->postJson(self::API . "/ordens/{$o->id}/garantia", ['causa' => 'peca', 'motivo' => 'Ruído', 'fora_do_prazo' => true])->assertCreated()->json('ordem_id');
        WorkOrderItem::create(['work_order_id' => $id, 'type' => 'service', 'name' => 'Rever', 'quantity' => 1, 'unit_price' => 10000, 'hours' => 2]);
        WorkOrder::whereKey($id)->update(['status' => 'completed', 'completed_at' => now(), 'total' => 10000]);

        $k = $this->getJson(self::API . '/indicadores?de=' . now()->subMonth()->toDateString() . '&ate=' . now()->toDateString())->assertOk()->json('actual');
        $this->assertSame(1, $k['retrabalho']['ordens']);
        // 2 h × 1 500 de custo para a oficina.
        $this->assertEquals(3000, $k['retrabalho']['custo']);
        $this->assertSame(1, collect($k['retrabalho']['por_causa'])->firstWhere('causa', 'peca')['ordens']);
        // A garantia não entra na receita nem no ticket médio.
        $this->assertSame(1, $k['ordens']);

        // Uma ordem aberta ainda não dá garantia.
        $aberta = $this->original(['status' => 'in_progress', 'completed_at' => null]);
        $this->postJson(self::API . "/ordens/{$aberta->id}/garantia", ['causa' => 'peca', 'motivo' => 'x'])->assertStatus(422);
    }
}
