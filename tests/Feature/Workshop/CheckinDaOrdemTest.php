<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderCheckin;
use Tests\TenantTestCase;

/**
 * O CHECK-IN DA VIATURA — como o carro chegou (15/09/2026, OF-01).
 */
class CheckinDaOrdemTest extends TenantTestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function api(WorkOrder $o): string
    {
        return "/api/v1/invoicing/react/oficina/ordens/{$o->id}/checkin";
    }

    private function ordem(int $kmDaViatura = 50000): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-CK', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active', 'mileage' => $kmDaViatura]);

        return WorkOrder::create(['order_number' => 'OS-CK-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Revisão.', 'status' => 'pending', 'priority' => 'normal', 'mileage_in' => $kmDaViatura]);
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'km' => 51200,
            'combustivel' => 3,
            'danos' => [['x' => 20.5, 'y' => 40, 'tipo' => 'amolgadela', 'nota' => 'porta traseira'], ['x' => 50, 'y' => 8, 'tipo' => 'risco']],
            'acessorios' => ['pneu_suplente', 'macaco'],
            'luzes' => ['motor'],
            'chaves' => 2,
            'objectos' => 'Óculos no porta-luvas',
            'notas' => 'Cliente com pressa',
        ], $troca);
    }

    public function test_gravar_o_check_in_guarda_tudo_e_os_km_vao_a_ordem_e_a_viatura(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        $this->getJson($this->api($o))->assertOk()->assertJsonPath('data.existe', false)->assertJsonPath('pode_editar', true);

        $r = $this->putJson($this->api($o), $this->dados())->assertOk();
        $r->assertJsonPath('data.existe', true)->assertJsonPath('data.km', 51200)->assertJsonPath('data.combustivel', 3)
            ->assertJsonPath('data.danos.0.nota', 'porta traseira')->assertJsonPath('data.danos.1.nota', null);
        $this->assertNotNull($r->json('message'));

        $this->assertSame(51200, $o->fresh()->mileage_in);
        $this->assertSame(51200, $o->vehicle->fresh()->mileage);
        $this->assertTrue($o->history()->where('description', 'like', '%heck-in%')->exists());

        // Menos km no check-in não tiram km à viatura.
        $this->putJson($this->api($o), $this->dados(['km' => 100]))->assertOk();
        $this->assertSame(100, $o->fresh()->mileage_in);
        $this->assertSame(51200, $o->vehicle->fresh()->mileage);
    }

    public function test_a_assinatura_vale_para_o_que_se_assinou(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        // Sem check-in gravado não se assina.
        $this->postJson($this->api($o) . '/assinatura', ['assinatura' => self::PNG, 'nome' => 'João'])->assertStatus(422);

        $this->putJson($this->api($o), $this->dados())->assertOk();
        $this->postJson($this->api($o) . '/assinatura', ['assinatura' => 'data:text/html;base64,PHNjcmlwdD4=', 'nome' => 'João'])->assertStatus(422)->assertJsonValidationErrors('assinatura');

        $this->postJson($this->api($o) . '/assinatura', ['assinatura' => self::PNG, 'nome' => 'João Manuel'])
            ->assertOk()->assertJsonPath('data.assinatura_valida', true)->assertJsonPath('data.assinado_por', 'João Manuel');

        // Uma nota interna não mexe no que o cliente assinou…
        $this->putJson($this->api($o), $this->dados(['notas' => 'Outra nota']))->assertOk()->assertJsonPath('data.assinatura_valida', true);

        // …um dano novo sim.
        $danos = [...$this->dados()['danos'], ['x' => 80, 'y' => 90, 'tipo' => 'partido']];
        $this->putJson($this->api($o), $this->dados(['notas' => 'Outra nota', 'danos' => $danos]))->assertOk()->assertJsonPath('data.assinatura_valida', false);

        $this->deleteJson($this->api($o) . '/assinatura')->assertOk()->assertJsonPath('data.assinatura', null);
    }

    public function test_so_tipos_acessorios_e_luzes_da_lista(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        $this->putJson($this->api($o), $this->dados(['danos' => [['x' => 10, 'y' => 10, 'tipo' => 'explodido']]]))->assertStatus(422)->assertJsonValidationErrors('danos.0.tipo');
        $this->putJson($this->api($o), $this->dados(['danos' => [['x' => 140, 'y' => 10, 'tipo' => 'risco']]]))->assertStatus(422)->assertJsonValidationErrors('danos.0.x');
        $this->putJson($this->api($o), $this->dados(['acessorios' => ['piscina']]))->assertStatus(422);
        $this->putJson($this->api($o), $this->dados(['combustivel' => 9]))->assertStatus(422);

        $this->assertSame(0, WorkOrderCheckin::count());
    }

    public function test_sem_permissao_de_editar_le_mas_nao_grava_e_outra_empresa_nao_ve(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $o = $this->ordem();

        $this->getJson($this->api($o))->assertOk()->assertJsonPath('pode_editar', false);
        $this->putJson($this->api($o), $this->dados())->assertForbidden();

        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheia = WorkOrder::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'order_number' => 'OS-X-' . substr(uniqid(), -5), 'vehicle_id' => $o->vehicle_id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'pending', 'priority' => 'normal']);
        $this->getJson($this->api($alheia))->assertNotFound();
    }

    public function test_a_folha_impressa_leva_o_check_in_e_a_assinatura(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $this->putJson($this->api($o), $this->dados())->assertOk();
        $this->postJson($this->api($o) . '/assinatura', ['assinatura' => self::PNG, 'nome' => 'João Manuel'])->assertOk();

        $this->get("/workshop/work-orders/{$o->id}/print")->assertOk()
            ->assertSee('CHECK-IN DA VIATURA')
            ->assertSee('porta traseira')
            ->assertSee('confirmado por João Manuel', false)
            ->assertSee('carro-planta.svg', false);
    }
}
