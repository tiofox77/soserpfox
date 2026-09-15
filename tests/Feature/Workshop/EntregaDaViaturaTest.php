<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\InspectionTemplate;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHandover;
use Tests\TenantTestCase;

/**
 * A ENTREGA DA VIATURA COM ASSINATURA (15/09/2026, OF-13).
 */
class EntregaDaViaturaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAMAASsJTYQAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(string $estado = 'completed'): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'EN', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Paulo Neto', 'brand' => 'Nissan', 'model' => 'Navara', 'status' => 'active', 'mileage' => 80000]);

        return WorkOrder::create(['order_number' => 'OS-EN-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'received_at' => now()->subDay(), 'problem_description' => 'x', 'status' => $estado, 'priority' => 'normal', 'mileage_in' => 80000, 'total' => 45000, 'completed_at' => now()]);
    }

    private function dados(array $mais = []): array
    {
        return $mais + ['km_saida' => 80012, 'combustivel' => 5, 'conferido' => ['trabalho_explicado', 'chaves_documentos'], 'nome' => 'Paulo Neto', 'documento' => '004512345LA041', 'notas' => ''];
    }

    public function test_mostra_o_dinheiro_e_a_entrada_e_grava_o_termo(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        $r = $this->getJson(self::API . "/{$o->id}/entrega")->assertOk();
        $this->assertFalse($r->json('data.existe'));
        $this->assertSame(80000, $r->json('entrada.km'));
        $this->assertEquals(45000, $r->json('contas.falta'));
        $this->assertNull($r->json('contas.factura'));
        $this->assertCount(count(WorkOrderHandover::CHECKLIST), $r->json('listas.checklist'));

        // Menos km do que à entrada não passa.
        $this->putJson(self::API . "/{$o->id}/entrega", $this->dados(['km_saida' => 79000]))->assertStatus(422)->assertJsonValidationErrors('km_saida');

        $this->putJson(self::API . "/{$o->id}/entrega", $this->dados())->assertOk()->assertJsonPath('data.km_saida', 80012);
        $this->assertSame(80012, $o->vehicle->fresh()->mileage);
        $this->assertSame(['trabalho_explicado', 'chaves_documentos'], WorkOrderHandover::where('work_order_id', $o->id)->value('checklist'));
    }

    public function test_assinar_e_entregar_passa_a_ordem_a_entregue_e_guarda_o_saldo_em_falta(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        $r = $this->postJson(self::API . "/{$o->id}/entrega/assinar", $this->dados(['assinatura' => self::PNG, 'entregar' => true]))->assertOk();

        $this->assertSame('delivered', $o->fresh()->status);
        $this->assertTrue($r->json('data.assinatura_valida'));
        $this->assertEquals(45000, $r->json('data.falta_ao_assinar'));
        $this->assertTrue($o->history()->where('description', 'like', '%levantou a viatura%')->exists());

        // Mudar o que se assinou deixa a assinatura sem valor.
        $this->putJson(self::API . "/{$o->id}/entrega", $this->dados(['km_saida' => 80100]))->assertOk()->assertJsonPath('data.assinatura_valida', false);

        $this->deleteJson(self::API . "/{$o->id}/entrega/assinatura")->assertOk()->assertJsonPath('data.assinatura', null);

        // O papel da ordem leva o termo.
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $this->get("/workshop/work-orders/{$o->id}/print")->assertOk()->assertSee('TERMO DE ENTREGA')->assertSee('004512345LA041');
    }

    public function test_sem_km_nao_assina_e_o_controlo_de_qualidade_obrigatorio_impede_entregar(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem('in_progress');
        $o->update(['completed_at' => null]);

        $this->postJson(self::API . "/{$o->id}/entrega/assinar", $this->dados(['km_saida' => null, 'assinatura' => self::PNG]))->assertStatus(422)->assertJsonValidationErrors('km_saida');

        InspectionTemplate::garantirCatalogo($this->tenant->id);
        InspectionTemplate::where('tenant_id', $this->tenant->id)->where('kind', 'qualidade')->update(['is_required' => true]);

        $this->postJson(self::API . "/{$o->id}/entrega/assinar", $this->dados(['assinatura' => self::PNG, 'entregar' => true]))->assertStatus(422)->assertJsonValidationErrors('entregar');
        $this->assertSame('in_progress', $o->fresh()->status);
        $this->assertNull(WorkOrderHandover::where('work_order_id', $o->id)->value('signature'));

        // Sem entregar, assina-se na mesma.
        $this->postJson(self::API . "/{$o->id}/entrega/assinar", $this->dados(['assinatura' => self::PNG, 'entregar' => false]))->assertOk();
        $this->assertSame('in_progress', $o->fresh()->status);
    }

    public function test_so_quem_edita_ordens_assina(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $o = $this->ordem();

        $this->getJson(self::API . "/{$o->id}/entrega")->assertOk()->assertJsonPath('pode_editar', false);
        $this->putJson(self::API . "/{$o->id}/entrega", $this->dados())->assertForbidden();
        $this->postJson(self::API . "/{$o->id}/entrega/assinar", $this->dados(['assinatura' => self::PNG]))->assertForbidden();
    }
}
