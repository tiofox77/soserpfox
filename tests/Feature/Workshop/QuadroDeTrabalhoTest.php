<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * O QUADRO DE TRABALHO (15/09/2026, OF-04).
 */
class QuadroDeTrabalhoTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    /**
     * SÓ LETRAS NOS IDENTIFICADORES, e não é um capricho.
     *
     * A matrícula era `LDA-{10..99}-{10..99}-QT` e o número da ordem levava
     * cinco caracteres de um `uniqid()` — que é hexadecimal, e portanto tem
     * dígitos. A procura do quadro varre a matrícula e o número da ordem, e o
     * ensaio procura «42»: de vez em quando um dos identificadores aleatórios
     * continha mesmo «42» e vinha uma ordem a mais. Dava uma falha em cada
     * sete corridas, sempre noutro sítio, sem nada a ver com o código.
     */
    private function semDigitos(int $quantos = 5): string
    {
        return substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPRSTUVWXYZ', 2)), 0, $quantos);
    }

    private function ordem(array $campos = [], array $viatura = []): WorkOrder
    {
        $v = Vehicle::create(array_merge(['plate' => 'LDA-' . $this->semDigitos(2) . '-' . $this->semDigitos(2) . '-QT', 'vehicle_number' => 'VEH-' . $this->semDigitos(), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active'], $viatura));

        return WorkOrder::create(array_merge(['order_number' => 'OS-QT-' . $this->semDigitos(), 'vehicle_id' => $v->id, 'received_at' => now()->subHours(3), 'problem_description' => 'x', 'status' => 'pending', 'priority' => 'normal'], $campos));
    }

    public function test_as_colunas_trazem_as_ordens_abertas_e_as_entregues_da_semana(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $urgente = $this->ordem(['status' => 'in_progress', 'priority' => 'urgent'], ['tag_number' => '42', 'work_order_ref' => '1050186']);
        $normal = $this->ordem(['status' => 'in_progress']);
        $this->ordem(['status' => 'cancelled']);
        $velha = $this->ordem(['status' => 'delivered', 'delivered_at' => now()->subDays(20)]);
        $recente = $this->ordem(['status' => 'delivered', 'delivered_at' => now()->subDay()]);
        WorkOrderItem::create(['work_order_id' => $urgente->id, 'type' => 'service', 'name' => 'Discos', 'quantity' => 1, 'unit_price' => 1000, 'approval' => 'pending']);

        $r = $this->getJson(self::API . '/quadro')->assertOk();
        $colunas = collect($r->json('colunas'))->keyBy('estado');

        $this->assertSame(['pending', 'scheduled', 'in_progress', 'waiting_parts', 'completed', 'delivered'], $colunas->keys()->all());
        $emCurso = $colunas['in_progress']['cartoes'];
        $this->assertSame([$urgente->id, $normal->id], array_column($emCurso, 'id'), 'o urgente vem primeiro');
        $this->assertSame(['42', '1050186', 1], [$emCurso[0]['tag'], $emCurso[0]['wo'], $emCurso[0]['a_espera']]);

        $entregues = array_column($colunas['delivered']['cartoes'], 'id');
        $this->assertContains($recente->id, $entregues);
        $this->assertNotContains($velha->id, $entregues);
        $this->assertFalse($r->json('pode_editar'));

        $this->assertSame([$urgente->id], array_column(collect($this->getJson(self::API . '/quadro?procura=42')->json('colunas'))->firstWhere('estado', 'in_progress')['cartoes'], 'id'));
    }

    public function test_atribuir_o_mecanico_e_filtrar_por_ele(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $m = Mechanic::create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos Mecânico', 'phone' => '923000000', 'is_active' => true]);
        $o = $this->ordem(['status' => 'in_progress']);
        $this->ordem(['status' => 'in_progress']);

        $this->postJson(self::API . "/{$o->id}/mecanico", ['mechanic_id' => $m->id])->assertOk();
        $this->assertSame($m->id, $o->fresh()->mechanic_id);
        $this->assertTrue($o->history()->where('description', 'like', '%Carlos Mecânico%')->exists());

        $comEle = collect($this->getJson(self::API . "/quadro?mecanico={$m->id}")->json('colunas'))->flatMap(fn ($c) => $c['cartoes']);
        $this->assertSame([$o->id], $comEle->pluck('id')->all());
        $this->assertCount(1, collect($this->getJson(self::API . '/quadro?mecanico=sem')->json('colunas'))->flatMap(fn ($c) => $c['cartoes']));

        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheio = Mechanic::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'name' => 'De fora', 'phone' => '923000001', 'is_active' => true]);
        $this->postJson(self::API . "/{$o->id}/mecanico", ['mechanic_id' => $alheio->id])->assertStatus(422);

        $this->postJson(self::API . "/{$o->id}/mecanico", ['mechanic_id' => null])->assertOk();
        $this->assertNull($o->fresh()->mechanic_id);
    }

    public function test_sem_permissao_de_editar_nao_atribui(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $o = $this->ordem();

        $this->postJson(self::API . "/{$o->id}/mecanico", ['mechanic_id' => null])->assertForbidden();
        $this->get('/workshop/board')->assertOk()->assertSee('data-ecra="oficina/quadro"', false);
    }
}
