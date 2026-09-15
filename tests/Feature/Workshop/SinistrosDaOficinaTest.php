<?php

namespace Tests\Feature\Workshop;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderClaim;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Workshop\SinistrosDaOficina;
use Tests\TenantTestCase;

/**
 * OS SINISTROS COM SEGURADORA (15/09/2026, OF-15).
 */
class SinistrosDaOficinaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'SN', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Helena Gomes', 'owner_nif' => '00' . random_int(1000000, 9999999) . 'LA041', 'brand' => 'Hyundai', 'model' => 'i10', 'status' => 'active', 'client_id' => $this->cliente->id]);
        $o = WorkOrder::create(['order_number' => 'OS-SN-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Colisão traseira', 'status' => 'completed', 'priority' => 'normal']);

        $peca = $this->produtoComStock(10, 80000);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'part', 'product_id' => $peca->id, 'name' => 'Pára-choques traseiro', 'quantity' => 1, 'unit_price' => 80000]);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Bate-chapa e pintura', 'quantity' => 3, 'unit_price' => 25000]);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Polimento', 'quantity' => 1, 'unit_price' => 5000]);

        return $o->fresh();
    }

    public function test_grava_o_sinistro_e_mostra_a_reparticao(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $seguradora = $this->clienteEmpresa();

        $r = $this->getJson(self::API . "/{$o->id}/sinistro")->assertOk();
        $this->assertNull($r->json('data'));
        $this->assertTrue(collect($r->json('seguradoras'))->contains('valor', (string) $seguradora->id));

        $this->putJson(self::API . "/{$o->id}/sinistro", ['seguradora_id' => 999999, 'estado' => 'aberto'])->assertStatus(422)->assertJsonValidationErrors('seguradora_id');

        $r = $this->putJson(self::API . "/{$o->id}/sinistro", [
            'seguradora_id' => $seguradora->id, 'processo' => 'SIN-2026-0042', 'apolice' => 'AP-77', 'data_sinistro' => today()->subDays(3)->toDateString(),
            'perito' => 'Eng. Lopes', 'perito_telefone' => '923555444', 'franquia' => 25000, 'valor_aprovado' => 150000, 'estado' => 'peritagem',
        ])->assertOk();

        $this->assertSame('SIN-2026-0042', $r->json('data.processo'));
        $this->assertEquals(25000, $r->json('reparticao.cliente'));
        $this->assertEquals(round((float) $o->total - 25000, 2), $r->json('reparticao.seguradora'));
        $this->assertSame('Cliente Empresa', $this->getJson(self::API . "/{$o->id}")->json('data.sinistro.seguradora'));
    }

    public function test_a_reparticao_soma_as_linhas_ao_centimo_e_tira_das_mais_caras(): void
    {
        $o = $this->ordem();
        $linhas = $o->linhasParaFacturar();
        $taxa = (float) \App\Services\Invoicing\TaxResolver::forProductId($linhas[0]['product_id'], $this->tenant->id)['rate'];
        $bruto = fn (array $ls) => round(array_sum(array_map(fn ($l) => round($l['quantity'] * $l['unit_price'] * (1 - $l['discount_percent'] / 100), 2), $ls)), 2);

        $r = SinistrosDaOficina::repartir($linhas, 57000, $this->tenant->id, 'SIN-1');

        $this->assertTrue($r['cabe']);
        // O líquido das duas partes é o da ordem.
        $this->assertEqualsWithDelta($bruto($linhas), $bruto($r['seguradora']) + $bruto($r['cliente']), 0.01);
        // A franquia (com imposto) sai da linha mais cara — os 3 × 25 000 da mão-de-obra ou os 80 000 da peça.
        $this->assertCount(1, $r['cliente']);
        $this->assertEqualsWithDelta(57000, $bruto($r['cliente']) * (1 + $taxa / 100), 0.02);
        $this->assertStringStartsWith('Franquia do sinistro SIN-1', $r['cliente'][0]['name']);

        // Uma franquia maior do que tudo não cabe.
        $this->assertFalse(SinistrosDaOficina::repartir($linhas, 10_000_000, $this->tenant->id, 'SIN-1')['cabe']);
    }

    public function test_facturar_o_sinistro_emite_a_da_seguradora_e_a_da_franquia(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'invoicing.sales.invoices.create');
        $o = $this->ordem();
        $seguradora = $this->clienteEmpresa();
        WorkOrderClaim::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $o->id, 'insurer_client_id' => $seguradora->id, 'claim_number' => 'SIN-9', 'excess_amount' => 30000, 'status' => 'aprovado']);

        $r = $this->postJson(self::API . "/{$o->id}/facturar")->assertOk();
        $this->assertStringContainsString('franquia do cliente', $r->json('message'));

        $o->refresh();
        $claim = WorkOrderClaim::where('work_order_id', $o->id)->first();
        $daSeguradora = SalesInvoice::find($o->invoice_id);
        $daFranquia = SalesInvoice::find($claim->excess_invoice_id);

        $this->assertSame($seguradora->id, $daSeguradora->client_id);
        $this->assertSame($this->cliente->id, $daFranquia->client_id);
        $this->assertEqualsWithDelta(30000, (float) $daFranquia->gross_total, 0.02);
        // As duas facturas somam o que a ordem vale (líquido e imposto).
        $sozinha = (float) $o->items()->where('approval', 'approved')->get()->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price);
        $this->assertEqualsWithDelta($sozinha, (float) $daSeguradora->net_total + (float) $daFranquia->net_total, 0.02);
        $this->assertStringContainsString('SIN-9', (string) $daSeguradora->notes);

        // A seguradora e a franquia já não mudam; e não se factura outra vez.
        $this->putJson(self::API . "/{$o->id}/sinistro", ['seguradora_id' => $seguradora->id, 'franquia' => 1000, 'estado' => 'pago'])->assertStatus(422);
        $this->putJson(self::API . "/{$o->id}/sinistro", ['seguradora_id' => $seguradora->id, 'franquia' => 30000, 'estado' => 'pago'])->assertOk();
        $this->postJson(self::API . "/{$o->id}/facturar")->assertStatus(422);
        $this->deleteJson(self::API . "/{$o->id}/sinistro")->assertStatus(422);
    }

    public function test_sem_franquia_sai_uma_factura_so_a_seguradora_e_sem_sinistro_tudo_como_antes(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'invoicing.sales.invoices.create');
        $o = $this->ordem();
        $seguradora = $this->clienteEmpresa();
        WorkOrderClaim::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $o->id, 'insurer_client_id' => $seguradora->id, 'excess_amount' => 0, 'status' => 'aprovado']);

        $this->postJson(self::API . "/{$o->id}/facturar")->assertOk()->assertJsonFragment(['message' => 'Factura ' . SalesInvoice::latest('id')->value('invoice_number') . ' emitida à seguradora.']);
        $this->assertNull(WorkOrderClaim::where('work_order_id', $o->id)->value('excess_invoice_id'));

        $normal = $this->ordem();
        $this->postJson(self::API . "/{$normal->id}/facturar")->assertOk();
        $this->assertSame($this->cliente->id, SalesInvoice::find($normal->fresh()->invoice_id)->client_id);
    }
}
