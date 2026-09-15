<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Models\Workshop\WorkOrderSurvey;
use Tests\TenantTestCase;

/**
 * O INQUÉRITO DE SATISFAÇÃO (15/09/2026, OF-14).
 */
class InqueritoDeSatisfacaoTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(?Mechanic $m = null, string $estado = 'completed'): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'IQ', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Marta Sousa', 'owner_phone' => '923777888', 'brand' => 'Kia', 'model' => 'Sportage', 'status' => 'active']);
        $o = WorkOrder::create(['order_number' => 'OS-IQ-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'mechanic_id' => $m?->id, 'received_at' => now()->subDay(), 'problem_description' => 'x', 'status' => $estado, 'priority' => 'normal', 'completed_at' => now()]);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Revisão geral', 'quantity' => 1, 'unit_price' => 30000]);

        return $o;
    }

    private function mecanico(string $nome): Mechanic
    {
        return Mechanic::create(['tenant_id' => $this->tenant->id, 'name' => $nome, 'phone' => '923000' . random_int(100, 999), 'level' => 'senior', 'is_active' => true]);
    }

    public function test_entregar_cria_o_inquerito_e_o_cliente_responde_uma_vez_pelo_link(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $m = $this->mecanico('Adilson Pedro');
        $o = $this->ordem($m);

        $this->postJson(self::API . "/ordens/{$o->id}/estado", ['estado' => 'delivered'])->assertOk();

        $s = WorkOrderSurvey::where('work_order_id', $o->id)->firstOrFail();
        $this->assertSame($m->id, $s->mechanic_id);
        $link = $this->getJson(self::API . "/ordens/{$o->id}/inquerito")->assertOk()->json('data.link');
        $this->assertStringEndsWith("/oficina/avaliar/{$s->token}", $link);

        // A página pública, sem conta.
        auth()->logout();
        $this->get("/oficina/avaliar/{$s->token}")->assertOk();
        $d = $this->getJson("/oficina/avaliar/{$s->token}/dados")->assertOk();
        $this->assertSame('Adilson', $d->json('ordem.mecanico'));
        $this->assertSame(['Revisão geral'], $d->json('ordem.servicos'));
        $this->assertNull($d->json('resposta'));

        $this->postJson("/oficina/avaliar/{$s->token}", ['recomenda' => true])->assertStatus(422)->assertJsonValidationErrors('nota');
        $this->postJson("/oficina/avaliar/{$s->token}", ['nota' => 4, 'recomenda' => true, 'comentario' => 'Rápidos e simpáticos'])->assertOk();
        $this->postJson("/oficina/avaliar/{$s->token}", ['nota' => 1])->assertStatus(422);

        $this->assertSame([4, true, 'Rápidos e simpáticos'], [$s->fresh()->score, $s->fresh()->would_recommend, $s->fresh()->comment]);
        $this->assertTrue($o->history()->where('description', 'like', '%avaliou o serviço com 4 estrelas%')->exists());

        $this->getJson('/oficina/avaliar/' . str_repeat('a', 48) . '/dados')->assertNotFound();
    }

    public function test_o_painel_e_o_mapa_dos_mecanicos_mostram_a_media(): void
    {
        $this->comPermissoes('workshop.dashboard.view', 'workshop.reports.view', 'workshop.work-orders.view', 'workshop.work-orders.edit');
        $a = $this->mecanico('Ana Mecânica');
        $b = $this->mecanico('Bruno Mecânico');

        foreach ([[$a, 5, true], [$a, 4, true], [$b, 2, false]] as [$m, $nota, $recomenda]) {
            $o = $this->ordem($m, 'delivered');
            \App\Services\Workshop\InqueritosDaOficina::criar($o)->update(['score' => $nota, 'would_recommend' => $recomenda, 'answered_at' => now(), 'comment' => "nota $nota"]);
        }

        $s = $this->getJson(self::API . '/painel')->assertOk()->json('satisfacao');
        $this->assertSame(3, $s['respostas']);
        $this->assertEquals(3.7, $s['media']);
        $this->assertSame(67, $s['recomendam']);
        $this->assertSame(2, collect($s['estrelas'])->whereIn('estrelas', [4, 5])->sum('quantos'));
        $this->assertCount(3, $s['ultimos']);

        $mapa = $this->getJson(self::API . '/relatorios?mapa=mecanicos&de=' . now()->subDay()->format('Y-m-d') . '&ate=' . now()->format('Y-m-d'))->assertOk();
        $linhas = collect($mapa->json('linhas'))->keyBy('nome');
        $this->assertEquals(4.5, $linhas['Ana Mecânica']['avaliacao']);
        $this->assertSame(2, $linhas['Ana Mecânica']['avaliacoes']);
        $this->assertEquals(2, $linhas['Bruno Mecânico']['avaliacao']);
    }

    public function test_o_link_a_mao_so_depois_de_concluida_e_so_quem_edita(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $aberta = $this->ordem(null, 'in_progress');

        $this->getJson(self::API . "/ordens/{$aberta->id}/inquerito")->assertOk()->assertJsonPath('pode_criar', false);
        $this->postJson(self::API . "/ordens/{$aberta->id}/inquerito")->assertStatus(422);

        $concluida = $this->ordem();
        $this->postJson(self::API . "/ordens/{$concluida->id}/inquerito")->assertOk()->assertJsonPath('data.nota', null);
        $this->assertSame(1, WorkOrderSurvey::where('work_order_id', $concluida->id)->count());

        $this->user->revokePermissionTo('workshop.work-orders.edit');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('permissions');
        $outra = $this->ordem();
        $this->postJson(self::API . "/ordens/{$outra->id}/inquerito")->assertForbidden();
    }
}
