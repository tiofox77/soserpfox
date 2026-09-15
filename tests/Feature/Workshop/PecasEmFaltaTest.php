<?php

namespace Tests\Feature\Workshop;

use App\Models\Compras\Requisicao;
use App\Models\Supplier;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Compras\FluxoDaEncomenda;
use App\Services\Compras\FluxoDaRequisicao;
use Tests\TenantTestCase;

/**
 * AS PEÇAS EM FALTA VIRAM REQUISIÇÃO, E A ORDEM ANDA QUANDO CHEGAM (15/09/2026, OF-08).
 */
class PecasEmFaltaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina')->comModulo('compras');
    }

    private function ordemComPeca(float $precisa, float $emStock): array
    {
        $produto = $this->produtoComStock($emStock);
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-PF', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);
        $o = WorkOrder::create(['order_number' => 'OS-PF-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
        $linha = WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'part', 'product_id' => $produto->id, 'name' => $produto->name, 'quantity' => $precisa, 'unit_price' => 5000]);

        return [$o, $linha, $produto];
    }

    public function test_a_ordem_diz_o_que_falta_no_armazem(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        [$o] = $this->ordemComPeca(5, 2);

        $r = $this->getJson(self::API . "/{$o->id}/pecas")->assertOk();
        $this->assertEqualsWithDelta(2, $r->json('pecas.0.em_stock'), 0.001);
        $this->assertEqualsWithDelta(3, $r->json('pecas.0.falta'), 0.001);
        $this->assertTrue($r->json('tem_compras'));
        $this->assertFalse($r->json('pode_pedir'));
    }

    public function test_pedir_cria_a_requisicao_ligada_e_a_recepcao_poe_a_ordem_em_curso(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'compras.requisicoes.manage');
        [$o, $linha, $produto] = $this->ordemComPeca(5, 2);

        $r = $this->postJson(self::API . "/{$o->id}/pecas/requisicao", ['linhas' => [['linha_id' => $linha->id, 'quantidade' => 3]], 'submeter' => true, 'esperar' => true])->assertCreated();

        $req = Requisicao::where('work_order_id', $o->id)->firstOrFail();
        $this->assertSame('submetida', $req->estado);
        $this->assertEqualsWithDelta(3, (float) $req->itens()->first()->quantidade, 0.001);
        $this->assertSame($produto->id, $req->itens()->first()->product_id);
        $this->assertSame('waiting_parts', $o->fresh()->status);
        $this->assertSame($req->numero, $r->json('requisicoes.0.numero'));

        // As Compras aprovam, encomendam e recebem.
        app(FluxoDaRequisicao::class)->aprovar($req, $this->tenant->id, $this->user->id);
        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Peças Lda', 'type' => 'pessoa_juridica', 'is_active' => true]);
        $fluxo = app(FluxoDaEncomenda::class);
        $enc = $fluxo->daRequisicao($req->fresh(), $this->tenant->id, $this->user->id, $fornecedor->id, ['warehouse_id' => $this->armazem->id]);
        $fluxo->enviar($enc, $this->tenant->id);
        $item = $enc->itens()->first();
        $fluxo->receber($enc->fresh(), $this->tenant->id, $this->user->id, [$item->id => 3]);

        $this->assertSame('in_progress', $o->fresh()->status);
        $this->assertTrue($o->history()->where('description', 'like', '%Chegaram as peças%')->exists());
        $this->assertEqualsWithDelta(0, $this->getJson(self::API . "/{$o->id}/pecas")->json('pecas.0.falta'), 0.001);
    }

    public function test_sem_permissao_das_compras_nao_pede_e_peca_de_outra_ordem_nao_entra(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        [$o, $linha] = $this->ordemComPeca(5, 2);

        $this->postJson(self::API . "/{$o->id}/pecas/requisicao", ['linhas' => [['linha_id' => $linha->id, 'quantidade' => 3]]])->assertForbidden();

        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'compras.requisicoes.manage');
        [, $outraLinha] = $this->ordemComPeca(1, 0);
        $this->postJson(self::API . "/{$o->id}/pecas/requisicao", ['linhas' => [['linha_id' => $outraLinha->id, 'quantidade' => 1]]])->assertStatus(422);
        $this->assertSame(0, Requisicao::where('work_order_id', $o->id)->count());
    }
}
