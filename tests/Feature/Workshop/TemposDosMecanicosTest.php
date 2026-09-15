<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\TimeEntry;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

/**
 * O REGISTO DE TEMPOS POR TAREFA (15/09/2026, OF-06).
 */
class TemposDosMecanicosTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ordem(string $estado = 'pending'): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-TP', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-TP-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => $estado, 'priority' => 'normal']);
    }

    private function mecanico(string $nome = 'Carlos'): Mechanic
    {
        return Mechanic::create(['tenant_id' => $this->tenant->id, 'name' => $nome, 'phone' => '923000000', 'is_active' => true]);
    }

    public function test_comecar_e_parar_conta_os_minutos_e_poe_a_ordem_em_curso(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $m = $this->mecanico();
        $linha = WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Travões', 'quantity' => 1, 'unit_price' => 20000, 'hours' => 2]);

        Carbon::setTestNow('2026-09-16 09:00:00');
        $r = $this->postJson(self::API . "/{$o->id}/tempos", ['mecanico_id' => $m->id, 'linha_id' => $linha->id])->assertCreated();
        $this->assertTrue($r->json('registos.0.a_correr'));
        $this->assertSame('in_progress', $o->fresh()->status);

        Carbon::setTestNow('2026-09-16 10:30:00');
        $id = $r->json('registos.0.id');
        $parado = $this->postJson(self::API . "/{$o->id}/tempos/$id/parar")->assertOk();
        $this->assertSame(90, TimeEntry::find($id)->minutes);
        $this->assertEqualsWithDelta(1.5, $parado->json('linhas.0.trabalhadas'), 0.01);
        $this->assertEqualsWithDelta(2, $parado->json('linhas.0.vendidas'), 0.01);
        $this->assertSame(133, $parado->json('contas.eficiencia'));

        $this->postJson(self::API . "/{$o->id}/tempos/$id/parar")->assertStatus(422);
    }

    public function test_comecar_noutra_ordem_para_o_relogio_que_estava_a_correr(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $a = $this->ordem('in_progress');
        $b = $this->ordem('in_progress');
        $m = $this->mecanico();

        Carbon::setTestNow('2026-09-16 08:00:00');
        $this->postJson(self::API . "/{$a->id}/tempos", ['mecanico_id' => $m->id])->assertCreated();
        Carbon::setTestNow('2026-09-16 08:45:00');
        $r = $this->postJson(self::API . "/{$b->id}/tempos", ['mecanico_id' => $m->id])->assertCreated();

        $this->assertStringContainsString($a->order_number, $r->json('message'));
        $this->assertSame(1, TimeEntry::where('mechanic_id', $m->id)->whereNull('ended_at')->count());
        $this->assertSame(45, TimeEntry::where('work_order_id', $a->id)->first()->minutes);

        // O quadro sabe quem está a trabalhar em que carro.
        $cartao = collect($this->getJson(self::API . '/quadro')->json('colunas'))->flatMap(fn ($c) => $c['cartoes'])->firstWhere('id', $b->id);
        $this->assertSame(['Carlos'], $cartao['a_trabalhar']);
    }

    public function test_corrigir_apagar_e_os_limites(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem('in_progress');
        $m = $this->mecanico();
        $t = TimeEntry::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $o->id, 'mechanic_id' => $m->id, 'started_at' => now()->subHours(10), 'ended_at' => now()->subHours(1)]);
        $this->assertSame(540, $t->minutes);

        $inicio = now()->subHours(3)->format('Y-m-d\TH:i');
        $this->putJson(self::API . "/{$o->id}/tempos/{$t->id}", ['inicio' => $inicio, 'fim' => now()->subHours(1)->format('Y-m-d\TH:i')])->assertOk();
        $this->assertSame(120, $t->fresh()->minutes);
        $this->assertTrue($o->history()->where('description', 'like', '%corrigido%')->exists());

        $this->putJson(self::API . "/{$o->id}/tempos/{$t->id}", ['inicio' => $inicio, 'fim' => now()->addHour()->format('Y-m-d\TH:i')])->assertStatus(422);
        $this->putJson(self::API . "/{$o->id}/tempos/{$t->id}", ['inicio' => $inicio, 'fim' => $inicio])->assertStatus(422);

        $this->deleteJson(self::API . "/{$o->id}/tempos/{$t->id}")->assertOk();
        $this->assertNull(TimeEntry::find($t->id));

        $fechada = $this->ordem('delivered');
        $this->postJson(self::API . "/{$fechada->id}/tempos", ['mecanico_id' => $m->id])->assertStatus(422);

        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheio = Mechanic::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'name' => 'De fora', 'phone' => '9', 'is_active' => true]);
        $this->postJson(self::API . "/{$o->id}/tempos", ['mecanico_id' => $alheio->id])->assertStatus(422);
    }

    public function test_o_mapa_dos_mecanicos_mostra_as_horas_e_a_eficiencia(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.reports.view');
        $o = $this->ordem('completed');
        $m = $this->mecanico('Adilson');
        $o->update(['mechanic_id' => $m->id]);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Revisão', 'quantity' => 1, 'unit_price' => 1000, 'hours' => 3]);
        TimeEntry::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $o->id, 'mechanic_id' => $m->id, 'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(3)]);

        $r = $this->getJson('/api/v1/invoicing/react/oficina/relatorios?mapa=mecanicos&de=' . now()->subDay()->format('Y-m-d') . '&ate=' . now()->format('Y-m-d'))->assertOk();
        $linha = collect($r->json('linhas'))->firstWhere('nome', 'Adilson');
        $this->assertEqualsWithDelta(2, $linha['trabalhadas'], 0.01);
        $this->assertEqualsWithDelta(3, $linha['vendidas'], 0.01);
        $this->assertSame(150, $linha['eficiencia']);
    }
}
