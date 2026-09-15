<?php

namespace Tests\Feature\Workshop;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\StockMovement;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderClaim;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * A FACTURAÇÃO DE FROTAS (15/09/2026, OF-16).
 */
class FacturacaoDeFrotasTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(\App\Models\Client $dono, string $matricula, string $estado = 'in_progress', ?\App\Models\Product $peca = null): WorkOrder
    {
        $v = Vehicle::firstOrCreate(['tenant_id' => $this->tenant->id, 'plate' => $matricula], ['vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => $dono->name, 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active', 'client_id' => $dono->id]);
        $o = WorkOrder::create(['order_number' => 'OS-FR-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'received_at' => now()->subDays(2), 'problem_description' => 'Revisão', 'status' => 'in_progress', 'priority' => 'normal']);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Revisão', 'quantity' => 1, 'unit_price' => 20000]);
        if ($peca) {
            WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'part', 'product_id' => $peca->id, 'name' => $peca->name, 'quantity' => 1, 'unit_price' => (float) $peca->price]);
        }
        if ($estado !== 'in_progress') {
            // Pela porta dos estados: é ela que tira as peças do stock.
            app(\App\Services\Workshop\OrdensDeServico::class)->aplicarEstado($o->fresh(), $estado);
        }

        return $o->fresh();
    }

    public function test_lista_as_empresas_e_as_ordens_que_podem_entrar(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $empresa = $this->clienteEmpresa();
        $pronta = $this->ordem($empresa, 'LD-11-11-FR', 'completed');
        $this->ordem($empresa, 'LD-22-22-FR', 'delivered');
        $emCurso = $this->ordem($empresa, 'LD-33-33-FR');
        $sinistro = $this->ordem($empresa, 'LD-44-44-FR', 'completed');
        WorkOrderClaim::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $sinistro->id, 'insurer_client_id' => $this->cliente->id, 'status' => 'aberto']);
        $comPendente = $this->ordem($empresa, 'LD-11-11-FR', 'completed');
        WorkOrderItem::create(['work_order_id' => $comPendente->id, 'type' => 'service', 'name' => 'Extra', 'quantity' => 1, 'unit_price' => 1000, 'approval' => 'pending']);

        $c = collect($this->getJson(self::API . '/frotas')->assertOk()->json('data'))->firstWhere('id', $empresa->id);
        $this->assertSame(3, $c['ordens']);
        $this->assertSame(2, $c['viaturas']);

        $ordens = collect($this->getJson(self::API . "/frotas/{$empresa->id}/ordens?de=" . today()->subMonth()->toDateString() . '&ate=' . today()->toDateString())->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($ordens[$pronta->id]['pode']);
        $this->assertArrayNotHasKey($emCurso->id, $ordens->all());
        $this->assertArrayHasKey($sinistro->id, $ordens->all());
        $this->assertFalse($ordens[$sinistro->id]['pode']);
        $this->assertFalse($ordens[$comPendente->id]['pode']);
    }

    public function test_uma_factura_com_as_ordens_marcadas_sem_descontar_o_stock_outra_vez(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'invoicing.sales.invoices.create');
        $empresa = $this->clienteEmpresa();
        $peca = $this->produtoComStock(10, 8000);
        $a = $this->ordem($empresa, 'LD-55-55-FR', 'completed', $peca);
        $b = $this->ordem($empresa, 'LD-66-66-FR', 'delivered', $peca);
        $saidasAntes = StockMovement::where('product_id', $peca->id)->where('type', 'out')->sum('quantity');
        $this->assertEquals(2, $saidasAntes);

        $r = $this->postJson(self::API . "/frotas/{$empresa->id}/facturar", ['ids' => [$a->id, $b->id]])->assertOk();

        $factura = SalesInvoice::findOrFail($r->json('factura.id'));
        $this->assertSame($empresa->id, $factura->client_id);
        $this->assertSame($factura->id, $a->fresh()->invoice_id);
        $this->assertSame($factura->id, $b->fresh()->invoice_id);
        $this->assertCount(4, $factura->items);
        $this->assertTrue($factura->items->contains(fn ($i) => str_starts_with($i->product_name, '[LD-55-55-FR · ' . $a->order_number . ']')));
        $this->assertStringStartsWith('FROTA-', (string) $factura->source_reference);
        // Retenção de IRT por ser empresa: 6,5% dos 40 000 de mão-de-obra.
        $this->assertEquals(2600, round((float) $factura->irt_amount, 2));
        // As peças não saem outra vez do stock.
        $this->assertEquals(2, StockMovement::where('product_id', $peca->id)->where('type', 'out')->sum('quantity'));

        // Já facturadas: outra vez não.
        $this->postJson(self::API . "/frotas/{$empresa->id}/facturar", ['ids' => [$a->id]])->assertStatus(422);
    }

    public function test_recusa_ordens_de_outro_cliente_e_so_com_permissao_da_facturacao(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $empresa = $this->clienteEmpresa();
        $outra = $this->ordem($this->cliente, 'LD-77-77-FR', 'completed');

        $this->postJson(self::API . "/frotas/{$empresa->id}/facturar", ['ids' => [$outra->id]])->assertForbidden();

        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->postJson(self::API . "/frotas/{$empresa->id}/facturar", ['ids' => [$outra->id]])->assertStatus(422);
        $this->assertNull($outra->fresh()->invoice_id);
    }
}
