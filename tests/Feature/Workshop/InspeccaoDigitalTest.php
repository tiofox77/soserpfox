<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\InspectionTemplate;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderInspection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * A INSPECÇÃO DIGITAL COM SEMÁFORO (15/09/2026, OF-02).
 */
class InspeccaoDigitalTest extends TenantTestCase
{
    private const CATALOGOS = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
        Storage::fake('public');
    }

    private function api(WorkOrder $o): string
    {
        return "/api/v1/invoicing/react/oficina/ordens/{$o->id}/inspeccoes";
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-IN', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-IN-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Revisão.', 'status' => 'in_progress', 'priority' => 'normal', 'recommendations' => 'Trocar escovas.']);
    }

    private function modeloPequeno(): InspectionTemplate
    {
        return InspectionTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'Rápida ' . uniqid(), 'points' => "Travões: Pastilhas\n- Pneus: Pneu da frente\nBuzina\n\n", 'is_active' => true]);
    }

    public function test_a_empresa_recebe_a_revisao_geral_e_o_modelo_le_os_pontos(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $o = $this->ordem();

        $r = $this->getJson($this->api($o))->assertOk();
        $this->assertSame(33, $r->json('modelos.0.pontos'));
        $this->assertTrue($r->json('modelos.0.padrao'));

        $m = $this->modeloPequeno();
        $this->assertSame(3, $m->points_count);
        $this->assertSame([
            ['seccao' => 'Travões', 'ponto' => 'Pastilhas'],
            ['seccao' => 'Pneus', 'ponto' => 'Pneu da frente'],
            ['seccao' => 'Geral', 'ponto' => 'Buzina'],
        ], InspectionTemplate::lerPontos($m->points));
    }

    public function test_comecar_marcar_e_concluir_so_com_todos_os_pontos_vistos(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $m = $this->modeloPequeno();

        $r = $this->postJson($this->api($o), ['modelo_id' => $m->id])->assertCreated();
        $id = $r->json('criada');
        $this->assertCount(3, $r->json('inspeccoes.0.pontos'));
        $this->assertSame(3, $r->json('inspeccoes.0.contas.por_ver'));

        $meio = [['estado' => 'ok', 'nota' => null], ['estado' => 'urgente', 'nota' => 'Pneu careca'], ['estado' => null, 'nota' => null]];
        $this->putJson($this->api($o) . "/$id", ['resultados' => $meio])->assertOk()->assertJsonPath('inspeccoes.0.contas.urgente', 1);
        $this->putJson($this->api($o) . "/$id", ['resultados' => $meio, 'concluir' => true])->assertStatus(422);
        $this->putJson($this->api($o) . "/$id", ['resultados' => [['estado' => 'ok']]])->assertStatus(422);

        $tudo = [['estado' => 'ok'], ['estado' => 'urgente', 'nota' => 'Pneu careca'], ['estado' => 'atencao', 'nota' => 'Fraca']];
        $this->putJson($this->api($o) . "/$id", ['resultados' => $tudo, 'concluir' => true])->assertOk()->assertJsonPath('inspeccoes.0.pontos.1.nota', 'Pneu careca');

        $this->assertNotNull(WorkOrderInspection::find($id)->completed_at);
        $this->assertTrue($o->history()->where('description', 'like', '%concluída%')->exists());
    }

    public function test_os_problemas_passam_as_recomendacoes_sem_repetir(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $id = $this->postJson($this->api($o), ['modelo_id' => $this->modeloPequeno()->id])->json('criada');
        $this->putJson($this->api($o) . "/$id", ['resultados' => [['estado' => 'atencao'], ['estado' => 'urgente', 'nota' => 'careca'], ['estado' => 'ok']], 'concluir' => true])->assertOk();

        $this->postJson($this->api($o) . "/$id/recomendar")->assertOk();
        $recomendacoes = $o->fresh()->recommendations;
        $this->assertStringStartsWith('Trocar escovas.', $recomendacoes);
        $this->assertStringContainsString('URGENTE — Pneus: Pneu da frente (careca)', $recomendacoes);
        $this->assertStringContainsString('Atenção — Travões: Pastilhas', $recomendacoes);

        $this->postJson($this->api($o) . "/$id/recomendar")->assertOk();
        $this->assertSame($recomendacoes, $o->fresh()->recommendations, 'carregar outra vez não repete');
    }

    public function test_fotografia_de_um_ponto_e_apagar_a_inspeccao_leva_as_fotos(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $id = $this->postJson($this->api($o), ['modelo_id' => $this->modeloPequeno()->id])->json('criada');

        $r = $this->post($this->api($o) . "/$id/pontos/1/foto", ['foto' => UploadedFile::fake()->image('pneu.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull($r->json('inspeccoes.0.pontos.1.foto'));
        $caminho = WorkOrderInspection::find($id)->results[1]['foto'];
        Storage::disk('public')->assertExists($caminho);

        $this->post($this->api($o) . "/$id/pontos/9/foto", ['foto' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])->assertNotFound();

        $this->deleteJson($this->api($o) . "/$id")->assertOk();
        Storage::disk('public')->assertMissing($caminho);
    }

    public function test_modelo_de_outra_empresa_nao_serve_e_sem_editar_nao_se_comeca(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheio = InspectionTemplate::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'name' => 'Deles', 'points' => 'A: b']);

        $this->postJson($this->api($o), ['modelo_id' => $alheio->id])->assertStatus(422);
    }

    public function test_sem_permissao_de_editar_ve_mas_nao_comeca(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $o = $this->ordem();

        $this->getJson($this->api($o))->assertOk()->assertJsonPath('pode_editar', false);
        $this->postJson($this->api($o), ['modelo_id' => $this->modeloPequeno()->id])->assertForbidden();
    }

    public function test_o_catalogo_dos_modelos_recusa_um_modelo_sem_pontos(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $this->getJson(self::CATALOGOS . '/modelos-de-inspeccao/opcoes')->assertOk();
        $this->postJson(self::CATALOGOS . '/modelos-de-inspeccao', ['name' => 'Vazio', 'points' => "\n  \n", 'is_active' => true])->assertStatus(422);
        $this->postJson(self::CATALOGOS . '/modelos-de-inspeccao', ['name' => 'Pré-compra', 'points' => "Carroçaria: Pintura\nMotor: Ruídos", 'is_active' => true])->assertCreated();

        $this->assertSame(2, InspectionTemplate::where('tenant_id', $this->tenant->id)->where('name', 'Pré-compra')->value('points_count'));
    }
}
