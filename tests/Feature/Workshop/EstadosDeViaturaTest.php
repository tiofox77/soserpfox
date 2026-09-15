<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehicleStatus;
use Tests\TenantTestCase;

/**
 * OS ESTADOS DE VIATURA — cada oficina cria os seus, e o estado muda-se na tabela (15/09/2026).
 */
class EstadosDeViaturaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
        VehicleStatus::esquecer();
    }

    private function viatura(array $campos = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-EV',
            'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active',
        ], $campos));
    }

    public function test_uma_oficina_sem_estados_recebe_os_padroes_e_o_formulario_le_os(): void
    {
        $this->comPermissoes('workshop.vehicles.view');

        $opcoes = $this->getJson(self::API . '/viaturas/opcoes')->assertOk();

        $estado = collect($opcoes->json('campos'))->firstWhere('chave', 'status');
        $codigos = array_column($estado['opcoes'], 'valor');
        $this->assertSame(['active', 'in_service', 'aguarda_orcamento', 'aguarda_pecas', 'pronta_entrega', 'completed', 'inactive', 'abatida'], $codigos);

        $coluna = collect($opcoes->json('colunas'))->firstWhere('chave', 'status');
        $this->assertTrue($coluna['rapido']);
        $this->assertSame('verde', $coluna['opcoes'][0]['cor']);
    }

    public function test_criar_um_estado_gera_o_codigo_e_a_viatura_aceita_o(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit', 'workshop.vehicles.create');
        $this->getJson(self::API . '/estados-de-viatura/opcoes')->assertOk();

        $this->postJson(self::API . '/estados-de-viatura', ['name' => 'Na pintura', 'color' => 'roxo', 'accepts_orders' => true, 'is_active' => true])->assertCreated();
        $this->assertTrue(VehicleStatus::where('tenant_id', $this->tenant->id)->where('code', 'na_pintura')->exists());

        $this->postJson(self::API . '/estados-de-viatura', ['name' => 'Na pintura', 'color' => 'roxo'])->assertStatus(422);

        $v = $this->viatura();
        $this->postJson(self::API . "/viaturas/{$v->id}/campo", ['chave' => 'status', 'valor' => 'na_pintura'])
            ->assertOk()->assertJsonPath('data.rotulos.status', 'Na pintura');
        $this->assertSame('na_pintura', $v->fresh()->status);
    }

    public function test_na_tabela_so_se_muda_para_um_estado_da_oficina_e_so_a_coluna_rapida(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $v = $this->viatura();

        $this->postJson(self::API . "/viaturas/{$v->id}/campo", ['chave' => 'status', 'valor' => 'nao_existe'])->assertStatus(422);
        $this->postJson(self::API . "/viaturas/{$v->id}/campo", ['chave' => 'owner_name', 'valor' => 'Outro'])->assertNotFound();
        $this->assertSame('active', $v->fresh()->status);

        // Um estado criado noutra oficina não serve aqui.
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        VehicleStatus::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'code' => 'so_la', 'name' => 'Só lá', 'color' => 'azul']);
        $this->postJson(self::API . "/viaturas/{$v->id}/campo", ['chave' => 'status', 'valor' => 'so_la'])->assertStatus(422);
    }

    public function test_sem_permissao_de_editar_nao_muda_na_tabela(): void
    {
        $this->comPermissoes('workshop.vehicles.view');
        $v = $this->viatura();

        $this->postJson(self::API . "/viaturas/{$v->id}/campo", ['chave' => 'status', 'valor' => 'inactive'])->assertForbidden();
    }

    public function test_um_estado_em_uso_nao_se_apaga(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        VehicleStatus::garantirCatalogo($this->tenant->id);
        $this->viatura(['status' => 'aguarda_pecas']);

        $emUso = VehicleStatus::where('tenant_id', $this->tenant->id)->where('code', 'aguarda_pecas')->first();
        $livre = VehicleStatus::where('tenant_id', $this->tenant->id)->where('code', 'abatida')->first();

        $this->deleteJson(self::API . "/estados-de-viatura/{$emUso->id}")->assertStatus(422);
        $this->deleteJson(self::API . "/estados-de-viatura/{$livre->id}")->assertOk();
    }

    public function test_a_viatura_abatida_nao_aparece_numa_ordem_nova(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        VehicleStatus::garantirCatalogo($this->tenant->id);

        $boa = $this->viatura(['status' => 'aguarda_orcamento']);
        $abatida = $this->viatura(['status' => 'abatida']);

        $viaturas = array_column($this->getJson('/api/v1/invoicing/react/oficina/ordens/opcoes')->assertOk()->json('viaturas'), 'valor');

        $this->assertContains((string) $boa->id, $viaturas);
        $this->assertNotContains((string) $abatida->id, $viaturas);
    }

    public function test_quem_nao_tem_sessao_no_portal_vai_a_entrada_do_portal(): void
    {
        auth()->logout();
        $this->get('/client/dashboard')->assertRedirect(route('client.login'));
    }
}
