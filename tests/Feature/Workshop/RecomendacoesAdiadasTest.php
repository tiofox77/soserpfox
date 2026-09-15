<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\DeferredItem;
use App\Models\Workshop\InspectionTemplate;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Workshop\LembretesDaOficina;
use Tests\TenantTestCase;

/**
 * AS RECOMENDAÇÕES ADIADAS (15/09/2026, OF-12).
 */
class RecomendacoesAdiadasTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function viatura(): Vehicle
    {
        return Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'RA', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Rosa Lima', 'owner_phone' => '923111222', 'brand' => 'Toyota', 'model' => 'RAV4', 'status' => 'active']);
    }

    private function ordem(Vehicle $v, string $estado = 'in_progress'): WorkOrder
    {
        return WorkOrder::create(['order_number' => 'OS-RA-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => $estado, 'priority' => 'normal']);
    }

    private function linha(WorkOrder $o, string $nome, float $preco, string $aprovacao = 'pending'): WorkOrderItem
    {
        return WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => $nome, 'quantity' => 1, 'unit_price' => $preco, 'approval' => $aprovacao]);
    }

    public function test_a_linha_recusada_fica_guardada_e_sai_se_a_decisao_voltar_atras(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $v = $this->viatura();
        $o = $this->ordem($v);
        $l = $this->linha($o, 'Discos de travão', 64000);

        $this->postJson(self::API . "/ordens/{$o->id}/linhas/{$l->id}/aprovacao", ['decisao' => 'declined'])->assertOk();

        $r = DeferredItem::where('work_order_item_id', $l->id)->firstOrFail();
        $this->assertSame(['recusada', 'pendente', $v->id], [$r->origin, $r->status, $r->vehicle_id]);
        $this->assertSame(today()->addDays(30)->toDateString(), $r->follow_up_on->toDateString());
        $this->assertEquals(64000, $r->valor());

        // Voltou atrás: já não é uma recusa.
        $this->postJson(self::API . "/ordens/{$o->id}/linhas/{$l->id}/aprovacao", ['decisao' => 'approved'])->assertOk();
        $this->assertFalse(DeferredItem::where('work_order_item_id', $l->id)->exists());
    }

    public function test_adiar_tira_a_linha_da_ordem_e_na_visita_seguinte_junta_se_com_um_clique(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $v = $this->viatura();
        $antiga = $this->ordem($v);
        $l = $this->linha($antiga, 'Correia de distribuição', 90000, 'approved');

        $this->postJson(self::API . "/ordens/{$antiga->id}/linhas/{$l->id}/adiar", ['voltar_em' => today()->addDays(60)->toDateString(), 'nota' => 'Mês que vem'])->assertOk();

        $this->assertFalse(WorkOrderItem::whereKey($l->id)->exists());
        $this->assertEquals(0, (float) $antiga->fresh()->total);
        $r = DeferredItem::where('vehicle_id', $v->id)->firstOrFail();
        $this->assertSame(['adiada', 'Mês que vem', null], [$r->origin, $r->note, $r->work_order_item_id]);

        // A ordem de origem não mostra o que nasceu nela; a nova mostra.
        $this->assertSame([], $this->getJson(self::API . "/ordens/{$antiga->id}/recomendacoes")->json('data'));
        $nova = $this->ordem($v);
        $this->assertSame('Correia de distribuição', $this->getJson(self::API . "/ordens/{$nova->id}/recomendacoes")->json('data.0.nome'));

        $this->postJson(self::API . "/ordens/{$nova->id}/recomendacoes/juntar", ['ids' => [$r->id], 'precisa_aprovacao' => true])->assertOk();
        $linha = WorkOrderItem::where('work_order_id', $nova->id)->firstOrFail();
        $this->assertSame(['Correia de distribuição', 'pending'], [$linha->name, $linha->approval]);
        $this->assertSame(['aceite', $nova->id], [$r->fresh()->status, $r->fresh()->resolved_work_order_id]);

        // Juntar outra vez não duplica.
        $this->postJson(self::API . "/ordens/{$nova->id}/recomendacoes/juntar", ['ids' => [$r->id]])->assertStatus(422);
    }

    public function test_os_pontos_da_inspeccao_passam_as_recomendacoes_sem_repetir(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $v = $this->viatura();
        $o = $this->ordem($v);
        InspectionTemplate::garantirCatalogo($this->tenant->id);
        $modelo = InspectionTemplate::where('tenant_id', $this->tenant->id)->where('kind', 'inspecao')->firstOrFail();

        $id = $this->postJson(self::API . "/ordens/{$o->id}/inspeccoes", ['modelo_id' => $modelo->id])->assertCreated()->json('criada');
        $pontos = count(\App\Models\Workshop\WorkOrderInspection::find($id)->results);
        $resultados = array_fill(0, $pontos, ['estado' => 'ok']);
        $resultados[0] = ['estado' => 'urgente', 'nota' => 'Pastilhas no fim'];
        $resultados[1] = ['estado' => 'atencao'];
        $this->putJson(self::API . "/ordens/{$o->id}/inspeccoes/$id", ['resultados' => $resultados, 'concluir' => true])->assertOk();

        $this->postJson(self::API . "/ordens/{$o->id}/inspeccoes/$id/recomendar")->assertOk();
        $this->postJson(self::API . "/ordens/{$o->id}/inspeccoes/$id/recomendar")->assertOk();

        $guardadas = DeferredItem::where('vehicle_id', $v->id)->where('origin', 'inspeccao')->get();
        $this->assertCount(2, $guardadas);
        $urgente = $guardadas->firstWhere('severity', 'urgente');
        $this->assertSame(today()->toDateString(), $urgente->follow_up_on->toDateString());
        $this->assertSame('Pastilhas no fim', $urgente->description);
    }

    public function test_a_lista_descarta_reabre_e_os_lembretes_chamam_o_cliente(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'workshop.vehicles.view', 'workshop.vehicles.edit');
        $v = $this->viatura();
        $o = $this->ordem($v, 'delivered');
        $a = $this->linha($o, 'Amortecedores', 120000, 'declined');
        $b = $this->linha($o, 'Escovas', 8000, 'declined');
        DeferredItem::query()->update(['follow_up_on' => today()->subDay()]);

        $r = $this->getJson(self::API . '/recomendacoes')->assertOk();
        $this->assertSame(2, $r->json('contas.pendentes'));
        $this->assertEquals(128000, $r->json('contas.valor_pendente'));
        $this->assertSame(2, $r->json('contas.para_propor'));
        $this->assertCount(2, $r->json('data.0.itens'));
        $this->assertEquals(128000, $r->json('data.0.valor'));

        // Nos Lembretes: uma linha para a viatura, com os dois trabalhos na mensagem.
        $item = collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-recomendacao");
        $this->assertTrue($item['vencido']);
        $this->assertSame(['Amortecedores', 'Escovas'], $item['trabalhos']);
        $this->assertStringContainsString('Amortecedores, Escovas', $item['mensagem']);
        $this->postJson(self::API . "/lembretes/{$v->id}/contacto", ['tipo' => 'recomendacao', 'canal' => 'whatsapp'])->assertOk();
        $this->assertNotNull(collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-recomendacao")['ultimo_contacto']);

        $rb = DeferredItem::where('work_order_item_id', $b->id)->firstOrFail();
        $this->postJson(self::API . "/recomendacoes/{$rb->id}/descartar", ['nota' => 'Já trocou'])->assertOk();
        $this->assertSame(1, $this->getJson(self::API . '/recomendacoes')->json('contas.pendentes'));
        $this->assertSame(['Escovas'], collect($this->getJson(self::API . '/recomendacoes?estado=descartada')->json('data.0.itens'))->pluck('nome')->all());

        // Uma nova lista de trabalhos é outro vencimento: o contacto anterior não a cobre.
        $this->assertSame(['Amortecedores'], collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-recomendacao")['trabalhos']);
        $this->assertNull(collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-recomendacao")['ultimo_contacto']);

        $this->postJson(self::API . "/recomendacoes/{$rb->id}/reabrir")->assertOk();
        $this->assertSame('pendente', $rb->fresh()->status);

        $ra = DeferredItem::where('work_order_item_id', $a->id)->firstOrFail();
        $this->putJson(self::API . "/recomendacoes/{$ra->id}", ['voltar_em' => today()->addMonths(3)->toDateString(), 'preco' => 110000, 'nota' => null])->assertOk();
        $this->assertEquals(110000, (float) $ra->fresh()->unit_price);
        $this->assertSame('Amortecedores', LembretesDaOficina::trabalhos(['Amortecedores']));
        $this->assertSame('A, B e mais 2', LembretesDaOficina::trabalhos(['A', 'B', 'C', 'D']));
    }

    public function test_so_quem_edita_ordens_mexe_e_outra_empresa_nao_ve(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $v = $this->viatura();
        $o = $this->ordem($v);
        $l = $this->linha($o, 'Filtro', 5000, 'declined');
        $r = DeferredItem::where('work_order_item_id', $l->id)->firstOrFail();

        $this->getJson(self::API . '/recomendacoes')->assertOk()->assertJsonPath('pode_gerir', false);
        $this->postJson(self::API . "/recomendacoes/{$r->id}/descartar")->assertForbidden();
        $this->postJson(self::API . "/ordens/{$o->id}/linhas/{$l->id}/adiar")->assertForbidden();

        $outra = \App\Models\Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $r->forceFill(['tenant_id' => $outra->id])->save();
        $this->assertSame(0, $this->getJson(self::API . '/recomendacoes')->json('contas.pendentes'));
    }
}
