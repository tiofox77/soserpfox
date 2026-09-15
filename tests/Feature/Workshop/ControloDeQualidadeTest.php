<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\InspectionTemplate;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderInspection;
use Tests\TenantTestCase;

/**
 * O CONTROLO DE QUALIDADE ANTES DE CONCLUIR (15/09/2026, OF-09).
 */
class ControloDeQualidadeTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-QC', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-QC-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    private function exigirQualidade(): InspectionTemplate
    {
        InspectionTemplate::garantirCatalogo($this->tenant->id);
        $qc = InspectionTemplate::where('tenant_id', $this->tenant->id)->where('kind', 'qualidade')->firstOrFail();
        $qc->update(['is_required' => true]);

        return $qc;
    }

    public function test_cada_oficina_recebe_um_controlo_de_qualidade_que_nao_e_obrigatorio(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        $r = $this->getJson(self::API . "/{$o->id}/inspeccoes")->assertOk();
        $qc = collect($r->json('modelos'))->firstWhere('tipo', 'qualidade');
        $this->assertNotNull($qc);
        $this->assertFalse($qc['obrigatorio']);
        $this->assertNull($r->json('qualidade.pendente'));

        // Sem obrigação, a ordem conclui como sempre.
        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();
    }

    public function test_obrigatorio_a_ordem_so_conclui_com_o_controlo_feito_e_sem_urgentes(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $qc = $this->exigirQualidade();
        $o = $this->ordem();

        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertStatus(422)->assertJsonFragment(['message' => 'Falta o controlo de qualidade: faça-o no separador Inspecção antes de concluir a ordem.']);
        $this->assertSame('in_progress', $o->fresh()->status);
        $this->assertNotNull($this->getJson(self::API . "/{$o->id}/inspeccoes")->json('qualidade.pendente'));

        // Entregar sem ter concluído também passa pela regra.
        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'delivered'])->assertStatus(422);

        $id = $this->postJson(self::API . "/{$o->id}/inspeccoes", ['modelo_id' => $qc->id])->assertCreated()->json('criada');
        $this->assertSame('qualidade', WorkOrderInspection::find($id)->kind);
        $pontos = count(WorkOrderInspection::find($id)->results);

        $comUrgente = array_fill(0, $pontos, ['estado' => 'ok']);
        $comUrgente[0] = ['estado' => 'urgente', 'nota' => 'Trava a puxar'];
        $this->putJson(self::API . "/{$o->id}/inspeccoes/$id", ['resultados' => $comUrgente, 'concluir' => true])->assertOk();
        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertStatus(422)->assertJsonFragment(['message' => 'O controlo de qualidade tem pontos urgentes: resolva-os e volte a fazê-lo antes de concluir.']);

        $this->putJson(self::API . "/{$o->id}/inspeccoes/$id", ['resultados' => array_fill(0, $pontos, ['estado' => 'ok']), 'reabrir' => true])->assertOk();
        $this->putJson(self::API . "/{$o->id}/inspeccoes/$id", ['resultados' => array_fill(0, $pontos, ['estado' => 'ok']), 'concluir' => true])->assertOk();
        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertSame('completed', $o->fresh()->status);
    }

    public function test_o_catalogo_so_deixa_obrigatorio_um_controlo_de_qualidade(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $this->getJson('/api/v1/invoicing/react/catalogos/modelos-de-inspeccao/opcoes')->assertOk();
        $this->postJson('/api/v1/invoicing/react/catalogos/modelos-de-inspeccao', ['name' => 'Entrada', 'kind' => 'inspecao', 'is_required' => true, 'points' => 'A: b', 'is_active' => true])->assertCreated();
        $this->assertFalse(InspectionTemplate::where('tenant_id', $this->tenant->id)->where('name', 'Entrada')->value('is_required'));

        $this->postJson('/api/v1/invoicing/react/catalogos/modelos-de-inspeccao', ['name' => 'Saída', 'kind' => 'qualidade', 'is_required' => true, 'points' => 'A: b', 'is_active' => true])->assertCreated();
        $this->assertTrue(InspectionTemplate::where('tenant_id', $this->tenant->id)->where('name', 'Saída')->value('is_required'));
    }
}
