<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehiclePhoto;
use App\Models\Workshop\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * AS FOTOGRAFIAS DA VIATURA — antes, durante, depois e danos (15/09/2026).
 */
class FotografiasDaViaturaTest extends TenantTestCase
{
    private const CATALOGOS = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
        Storage::fake('public');
    }

    private function api(Vehicle $v): string
    {
        return "/api/v1/invoicing/react/oficina/viaturas/{$v->id}/fotografias";
    }

    private function viatura(array $campos = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-FV',
            'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active',
        ], $campos));
    }

    private function ordem(Vehicle $v, string $numero = 'OS-F-1'): WorkOrder
    {
        return WorkOrder::create(['order_number' => $numero . '-' . substr(uniqid(), -4), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Porta amolgada.', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    public function test_juntar_um_lote_grava_a_fase_o_servico_a_zona_e_a_folha(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $v = $this->viatura();
        $o = $this->ordem($v);

        $r = $this->post($this->api($v), [
            'fotografias' => [UploadedFile::fake()->image('porta.jpg', 800, 600), UploadedFile::fake()->image('capo.png', 640, 480)],
            'fase' => 'antes', 'servico' => 'bate_chapa', 'zona' => 'lateral_esquerda', 'ordem_id' => $o->id, 'descricao' => 'Amolgadela',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertCount(2, $r->json('data'));
        $foto = VehiclePhoto::where('vehicle_id', $v->id)->first();
        $this->assertSame($this->tenant->id, $foto->tenant_id);
        $this->assertSame(['antes', 'bate_chapa', 'lateral_esquerda', $o->id, 800, 600], [$foto->phase, $foto->service, $foto->zone, $foto->work_order_id, $foto->width, $foto->height]);
        Storage::disk('public')->assertExists($foto->file_path);

        // A folha de obra fica a saber, no histórico.
        $this->assertTrue($o->history()->where('description', 'like', '%fotografia%')->exists());

        $lista = $this->getJson($this->api($v))->assertOk();
        $this->assertCount(2, $lista->json('fotos'));
        $this->assertSame($o->order_number, $lista->json('fotos.0.ordem'));
        $this->assertContains('bate_chapa', array_column($lista->json('listas.servicos'), 'valor'));
        $this->assertTrue($lista->json('pode_editar'));
    }

    public function test_so_imagens_e_so_folhas_desta_viatura(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $v = $this->viatura();
        $outra = $this->viatura();
        $alheia = $this->ordem($outra);

        $this->post($this->api($v), ['fotografias' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')], 'fase' => 'antes', 'servico' => 'pintura'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('fotografias.0');

        $this->post($this->api($v), ['fotografias' => [UploadedFile::fake()->image('a.jpg')], 'fase' => 'antes', 'servico' => 'pintura', 'ordem_id' => $alheia->id], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('ordem_id');

        $this->post($this->api($v), ['fotografias' => [UploadedFile::fake()->image('a.jpg')], 'fase' => 'qualquer', 'servico' => 'pintura'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('fase');

        $this->assertSame(0, VehiclePhoto::count());
    }

    public function test_mudar_e_tirar_apaga_o_ficheiro(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $v = $this->viatura();

        $this->post($this->api($v), ['fotografias' => [UploadedFile::fake()->image('a.jpg')], 'fase' => 'antes', 'servico' => 'pintura'], ['Accept' => 'application/json'])->assertCreated();
        $foto = VehiclePhoto::first();

        $this->putJson($this->api($v) . "/{$foto->id}", ['fase' => 'depois', 'servico' => 'polimento', 'zona' => 'capo', 'descricao' => 'Pronto'])
            ->assertOk()->assertJsonPath('data.fase', 'depois')->assertJsonPath('data.zona', 'capo');

        $this->deleteJson($this->api($v) . "/{$foto->id}")->assertOk();
        $this->assertNull(VehiclePhoto::find($foto->id));
        Storage::disk('public')->assertMissing($foto->file_path);
    }

    public function test_sem_permissao_de_editar_ve_mas_nao_junta(): void
    {
        $this->comPermissoes('workshop.vehicles.view');
        $v = $this->viatura();

        $this->getJson($this->api($v))->assertOk()->assertJsonPath('pode_editar', false);
        $this->post($this->api($v), ['fotografias' => [UploadedFile::fake()->image('a.jpg')], 'fase' => 'antes', 'servico' => 'pintura'], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_a_viatura_de_outra_empresa_nao_se_ve(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheia = Vehicle::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'plate' => 'LD-99-99-ZZ', 'vehicle_number' => 'VEH-Z' . substr(uniqid(), -4), 'owner_name' => 'X', 'brand' => 'A', 'model' => 'B', 'status' => 'active']);

        $this->getJson($this->api($alheia))->assertNotFound();
        $this->post($this->api($alheia), ['fotografias' => [UploadedFile::fake()->image('a.jpg')], 'fase' => 'antes', 'servico' => 'pintura'], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_o_formulario_da_viatura_tem_separadores_e_o_cliente_preenche_o_dono(): void
    {
        $this->comPermissoes('workshop.vehicles.view');
        $cliente = $this->clienteEmpresa();
        $cliente->forceFill(['phone' => '923000111', 'email' => 'dono@exemplo.ao', 'address' => 'Rua 1'])->save();

        $opcoes = $this->getJson(self::CATALOGOS . '/viaturas/opcoes')->assertOk();

        $this->assertSame(['viatura', 'dono', 'documentos'], array_column($opcoes->json('grupos'), 'chave'));

        // Nenhum campo fica sem separador.
        $nosGrupos = array_merge(...array_column($opcoes->json('grupos'), 'campos'));
        $this->assertEqualsCanonicalizing(array_column($opcoes->json('campos'), 'chave'), $nosGrupos);

        $campo = collect($opcoes->json('campos'))->firstWhere('chave', 'client_id');
        $this->assertSame('telefone', $campo['preencher']['owner_phone']);

        $op = collect($opcoes->json('referencias.clientes'))->firstWhere('valor', (string) $cliente->id);
        $this->assertSame('923000111', $op['dados']['telefone']);
        $this->assertNotEmpty($opcoes->json('referencias.fotos_zonas'));
    }

    public function test_o_wo_e_o_tag_gravam_se_aparecem_na_lista_e_procuram_se(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create');

        $opcoes = $this->getJson(self::CATALOGOS . '/viaturas/opcoes')->assertOk();
        $colunas = array_column($opcoes->json('colunas'), 'rotulo', 'chave');
        $this->assertSame('WO#', $colunas['work_order_ref']);
        $this->assertSame('TAG#', $colunas['tag_number']);

        $this->postJson(self::CATALOGOS . '/viaturas', [
            'plate' => 'lda2862rp', 'work_order_ref' => ' 1050186 ', 'tag_number' => '42a',
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active',
        ])->assertCreated();

        $v = Vehicle::where('plate', 'LDA2862RP')->firstOrFail();
        $this->assertSame(['1050186', '42A'], [$v->work_order_ref, $v->tag_number]);

        $this->assertContains('LDA2862RP', array_column($this->getJson(self::CATALOGOS . '/viaturas?procura=1050186')->assertOk()->json('data'), 'plate'));
        $linha = collect($this->getJson(self::CATALOGOS . '/viaturas?procura=42A')->assertOk()->json('data'))->firstWhere('plate', 'LDA2862RP');
        $this->assertSame('1050186', $linha['work_order_ref']);
    }
}
