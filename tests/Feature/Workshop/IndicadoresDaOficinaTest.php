<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\Mechanic;
use App\Models\Workshop\TimeEntry;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * OS INDICADORES DA OFICINA (15/09/2026, OF-18).
 */
class IndicadoresDaOficinaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/indicadores';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(array $mais, array $linhas): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'KP', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Dono', 'brand' => 'Kia', 'model' => 'Rio', 'status' => 'active']);
        $o = WorkOrder::create($mais + ['order_number' => 'OS-KP-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'problem_description' => 'x', 'priority' => 'normal']);
        foreach ($linhas as $l) {
            WorkOrderItem::create($l + ['work_order_id' => $o->id, 'quantity' => 1]);
        }
        $o->refresh();
        // O total da ordem é o que as linhas aprovadas somam (sem imposto nos testes: a conta é por linhas).
        $o->forceFill(['total' => $o->items()->where('approval', 'approved')->sum('subtotal')])->saveQuietly();

        return $o->fresh();
    }

    public function test_as_contas_do_periodo_e_a_comparacao_com_o_anterior(): void
    {
        $this->comPermissoes('workshop.reports.view');
        $mecanico = Mechanic::create(['tenant_id' => $this->tenant->id, 'name' => 'Paulo', 'phone' => '923111000', 'level' => 'senior', 'hourly_rate' => 2000, 'is_active' => true]);
        $peca = $this->produtoComStock(10, 10000);
        $peca->update(['cost' => 6000]);

        // Duas ordens fechadas neste mês: uma em 4 h, da entrada ao fecho, e outra em 44 h.
        $a = $this->ordem(['status' => 'completed', 'received_at' => now()->startOfMonth()->addHours(8), 'completed_at' => now()->startOfMonth()->addHours(12), 'mechanic_id' => $mecanico->id], [
            ['type' => 'service', 'name' => 'Revisão', 'unit_price' => 20000, 'hours' => 2, 'mechanic_id' => $mecanico->id],
            ['type' => 'part', 'name' => 'Filtro', 'product_id' => $peca->id, 'unit_price' => 10000],
            ['type' => 'service', 'name' => 'Recusado', 'unit_price' => 5000, 'approval' => 'declined', 'approval_at' => now()->startOfMonth()->addHours(9)],
        ]);
        $this->ordem(['status' => 'delivered', 'received_at' => now()->startOfMonth()->addHours(8), 'delivered_at' => now()->startOfMonth()->addDays(1)->addHours(28), 'mechanic_id' => $mecanico->id], [
            ['type' => 'service', 'name' => 'Travões', 'unit_price' => 10000, 'hours' => 1, 'approval' => 'approved', 'approval_at' => now()->startOfMonth()->addHours(10)],
        ]);
        // Uma aberta não conta.
        $this->ordem(['status' => 'in_progress', 'received_at' => now()], [['type' => 'service', 'name' => 'Aberta', 'unit_price' => 99999]]);
        // O relógio: 4 h trabalhadas na ordem A.
        TimeEntry::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $a->id, 'mechanic_id' => $mecanico->id, 'started_at' => now()->startOfMonth()->addHours(8), 'ended_at' => now()->startOfMonth()->addHours(12)]);

        // No mês passado, uma ordem de 15 000.
        $this->ordem(['status' => 'completed', 'received_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->addHours(8), 'completed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->addHours(10)], [
            ['type' => 'service', 'name' => 'Antiga', 'unit_price' => 15000, 'hours' => 1],
        ]);

        $r = $this->getJson(self::API . '?de=' . now()->startOfMonth()->toDateString() . '&ate=' . now()->endOfMonth()->toDateString())->assertOk();
        $x = $r->json('actual');

        $this->assertSame(2, $x['ordens']);
        $this->assertEquals(40000, $x['receita']);
        $this->assertEquals(20000, $x['ticket_medio']);
        $this->assertEquals(24.0, $x['tempo_medio_horas']);
        // Peças: vendidas 10 000, custo 6 000 → 40%.
        $this->assertEquals(40.0, $x['pecas']['margem']);
        // Mão-de-obra: 30 000 vendidos, 4 h × 2 000 = 8 000 de custo.
        $this->assertEquals(8000, $x['mao_de_obra']['custo']);
        $this->assertEquals(73.3, $x['mao_de_obra']['margem']);
        $this->assertSame(['vendidas' => 3, 'trabalhadas' => 4, 'eficiencia' => 75], array_map(fn ($v) => is_float($v) ? (int) $v : $v, $x['horas']));
        // Aprovação: 10 000 aprovados de 15 000 decididos.
        $this->assertEquals(66.7, $x['aprovacao']['taxa']);

        $this->assertEquals(15000, $r->json('anterior.receita'));
        $this->assertCount(12, $r->json('meses'));
    }

    public function test_so_com_a_permissao_dos_relatorios(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $this->getJson(self::API)->assertForbidden();
    }
}
