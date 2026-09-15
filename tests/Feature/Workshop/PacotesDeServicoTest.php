<?php

namespace Tests\Feature\Workshop;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Workshop\ServicePackage;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * OS PACOTES DE SERVIÇO (15/09/2026, OF-07).
 */
class PacotesDeServicoTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-PC', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-PC-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    private function revisao(): array
    {
        return [
            'nome' => 'Revisão 10 000 km',
            'descricao' => 'Óleo e filtros',
            'activo' => true,
            'linhas' => [
                ['tipo' => 'service', 'nome' => 'Mão-de-obra da revisão', 'quantidade' => 1, 'preco' => 15000, 'horas' => 1.5],
                ['tipo' => 'part', 'nome' => 'Óleo 5W30', 'quantidade' => 4, 'preco' => 3500, 'desconto' => 10],
            ],
        ];
    }

    public function test_criar_um_pacote_calcula_o_total_e_as_horas(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.services.edit');

        $r = $this->postJson(self::API . '/pacotes', $this->revisao())->assertCreated();
        $this->assertEqualsWithDelta(15000 + 4 * 3500 * 0.9, $r->json('data.total'), 0.01);
        $this->assertEqualsWithDelta(1.5, $r->json('data.horas'), 0.01);

        $this->postJson(self::API . '/pacotes', $this->revisao())->assertStatus(422)->assertJsonValidationErrors('nome');
        $this->postJson(self::API . '/pacotes', ['nome' => 'Vazio', 'linhas' => []])->assertStatus(422)->assertJsonValidationErrors('linhas');

        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheio = Product::withoutGlobalScopes()->forceCreate(['tenant_id' => $outra->id, 'name' => 'Peça deles', 'code' => 'PD' . uniqid(), 'price' => 10, 'type' => 'produto']);
        $dados = $this->revisao();
        $dados['nome'] = 'Com peça alheia';
        $dados['linhas'][1]['product_id'] = $alheio->id;
        $this->postJson(self::API . '/pacotes', $dados)->assertStatus(422)->assertJsonValidationErrors('linhas.1.product_id');
    }

    public function test_juntar_o_pacote_a_ordem_poe_as_linhas_e_os_totais(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'workshop.services.edit');
        $p = ServicePackage::find($this->postJson(self::API . '/pacotes', $this->revisao())->json('data.id'));
        $o = $this->ordem();

        $this->postJson(self::API . "/ordens/{$o->id}/pacotes", ['pacote_id' => $p->id])->assertOk();
        $linhas = WorkOrderItem::where('work_order_id', $o->id)->orderBy('id')->get();
        $this->assertSame(['service', 'part'], $linhas->pluck('type')->all());
        $this->assertEqualsWithDelta(1.5, (float) $linhas[0]->hours, 0.01);
        $this->assertEqualsWithDelta(15000 + 12600, (float) $o->fresh()->total, 0.01);
        $this->assertSame(1, $p->fresh()->times_used);

        // Com aprovação, entram à espera e fora do total.
        $outra = $this->ordem();
        $this->postJson(self::API . "/ordens/{$outra->id}/pacotes", ['pacote_id' => $p->id, 'precisa_aprovacao' => true])->assertOk();
        $this->assertSame(['pending', 'pending'], WorkOrderItem::where('work_order_id', $outra->id)->pluck('approval')->all());
        $this->assertEqualsWithDelta(0, (float) $outra->fresh()->total, 0.01);

        // Um pacote desactivado não entra.
        $p->update(['is_active' => false]);
        $this->postJson(self::API . "/ordens/{$o->id}/pacotes", ['pacote_id' => $p->id])->assertStatus(422);
    }

    public function test_guardar_uma_ordem_como_pacote(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'workshop.services.edit');
        $o = $this->ordem();
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Travões', 'quantity' => 1, 'unit_price' => 20000, 'hours' => 2]);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'part', 'name' => 'Proposta recusada', 'quantity' => 1, 'unit_price' => 50000, 'approval' => 'declined']);

        $r = $this->postJson(self::API . "/ordens/{$o->id}/guardar-pacote", ['nome' => 'Travões da frente'])->assertCreated();
        $this->assertCount(1, $r->json('data.linhas'), 'só as linhas aprovadas');
        $this->assertEqualsWithDelta(20000, $r->json('data.total'), 0.01);
    }

    public function test_sem_permissao_de_servicos_nao_gere_pacotes(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $this->getJson(self::API . '/pacotes')->assertOk()->assertJsonPath('pode_gerir', false);
        $this->postJson(self::API . '/pacotes', $this->revisao())->assertForbidden();
        $this->get('/workshop/packages')->assertOk()->assertSee('data-ecra="oficina/pacotes"', false);
    }
}
