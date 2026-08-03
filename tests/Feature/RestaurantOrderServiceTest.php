<?php

namespace Tests\Feature;

use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantKitchenService;
use App\Services\Restaurant\RestaurantCheckoutService;
use App\Services\Restaurant\RestaurantReservationService;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

class RestaurantOrderServiceTest extends TenantTestCase
{
    private Venue $venue;
    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
        ]);
        $this->venue = Venue::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRINCIPAL',
            'name' => 'Restaurante Principal',
            'warehouse_id' => $this->armazem->id,
        ]);
        $area = Area::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'name' => 'Sala',
        ]);
        $this->table = DiningTable::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->venue->id,
            'area_id' => $area->id,
            'code' => 'M01',
            'name' => 'Mesa 01',
            'capacity' => 4,
        ]);
    }

    private function service(): RestaurantOrderService
    {
        return app(RestaurantOrderService::class);
    }

    public function test_abre_comanda_com_numero_do_tenant_e_ocupa_mesa(): void
    {
        $order = $this->service()->open([
            'venue_id' => $this->venue->id,
            'table_id' => $this->table->id,
            'guest_count' => 3,
            'local_uuid' => 'restaurant-test-uuid-1',
        ], $this->tenant->id, $this->user->id);

        $this->assertStringStartsWith('CMD-', $order->order_number);
        $this->assertSame('draft', $order->status);
        $this->assertSame(3, $order->guest_count);
        $this->assertSame('occupied', $this->table->fresh()->status);
        $this->assertDatabaseHas('restaurant_order_events', [
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'event' => 'order_opened',
        ]);
    }

    public function test_repetir_uuid_devolve_a_mesma_comanda(): void
    {
        $payload = [
            'venue_id' => $this->venue->id,
            'table_id' => $this->table->id,
            'local_uuid' => 'restaurant-idempotent-uuid',
        ];

        $first = $this->service()->open($payload, $this->tenant->id, $this->user->id);
        $second = $this->service()->open($payload, $this->tenant->id, $this->user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('local_uuid', 'restaurant-idempotent-uuid')
            ->count());
    }

    public function test_artigo_usa_taxresolver_e_actualiza_totais(): void
    {
        $product = $this->produtoComStock(10, 1000);
        $order = $this->service()->open([
            'venue_id' => $this->venue->id,
            'table_id' => $this->table->id,
        ], $this->tenant->id, $this->user->id);

        $item = $this->service()->addItem(
            $order, $product->id, 2, 'Sem cebola', $this->tenant->id, $this->user->id
        );

        $this->assertEquals(14, (float) $item->tax_rate);
        $this->assertEquals(280, (float) $item->tax_amount);
        $this->assertEquals(2280, (float) $order->fresh()->grand_total);
        $this->assertSame('Sem cebola', $item->notes);
    }

    public function test_confirmar_envia_linhas_para_cozinha_e_bloqueia_remocao(): void
    {
        $product = $this->produtoComStock(10, 1000);
        $order = $this->service()->open([
            'venue_id' => $this->venue->id,
            'table_id' => $this->table->id,
        ], $this->tenant->id, $this->user->id);
        $item = $this->service()->addItem($order, $product->id, 1, null, $this->tenant->id, $this->user->id);

        $confirmed = $this->service()->confirm($order, $this->tenant->id, $this->user->id);

        $this->assertSame('in_preparation', $confirmed->status);
        $this->assertSame('queued', $item->fresh()->kitchen_status);
        $this->assertSame('waiting_kitchen', $this->table->fresh()->status);
        $this->assertDatabaseHas('restaurant_kitchen_tickets', [
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'status' => 'queued',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->removeItem($item->fresh(), $this->tenant->id, $this->user->id, 'Tentativa tardia');
    }

    public function test_nao_abre_com_mesa_de_outro_tenant(): void
    {
        $other = \App\Models\Tenant::create([
            'name' => 'Outro Restaurante', 'slug' => 'outro-rest-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'outro' . uniqid() . '@example.ao', 'is_active' => true,
        ]);
        $otherVenue = Venue::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'code' => 'PRINCIPAL', 'name' => 'Outro',
        ]);
        $otherTable = DiningTable::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'venue_id' => $otherVenue->id,
            'code' => 'M01', 'name' => 'Mesa de Outro',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->open([
            'venue_id' => $this->venue->id,
            'table_id' => $otherTable->id,
        ], $this->tenant->id, $this->user->id);
    }

    public function test_fluxo_da_cozinha_actualiza_ticket_comanda_e_mesa(): void
    {
        $product = $this->produtoComStock(10, 1000);
        $order = $this->service()->open(['venue_id' => $this->venue->id, 'table_id' => $this->table->id], $this->tenant->id, $this->user->id);
        $this->service()->addItem($order, $product->id, 1, null, $this->tenant->id, $this->user->id);
        $this->service()->confirm($order, $this->tenant->id, $this->user->id);

        $ticket = \App\Models\Restaurant\KitchenTicket::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $kitchen = app(RestaurantKitchenService::class);
        foreach (['accepted', 'preparing', 'ready', 'served'] as $state) {
            $ticket = $kitchen->transition($ticket, $state, $this->tenant->id, $this->user->id);
        }

        $this->assertSame('served', $ticket->status);
        $this->assertSame('served', $order->fresh()->status);
        $this->assertSame('served', $this->table->fresh()->status);
        $this->assertDatabaseHas('restaurant_order_events', ['order_id' => $order->id, 'event' => 'kitchen_served']);
    }

    public function test_checkout_ft_liga_linhas_e_e_idempotente(): void
    {
        $product = $this->produtoComStock(10, 1000);
        $order = $this->service()->open(['venue_id' => $this->venue->id, 'table_id' => $this->table->id], $this->tenant->id, $this->user->id);
        $item = $this->service()->addItem($order, $product->id, 2, null, $this->tenant->id, $this->user->id);
        $order->update(['status' => 'served']);
        $key = (string) Str::uuid();
        $checkout = app(RestaurantCheckoutService::class);
        $first = $checkout->checkout($order, ['document_type' => 'FT', 'client_id' => $this->cliente->id, 'idempotency_key' => $key], $this->tenant->id, $this->user->id);
        $second = $checkout->checkout($order->fresh(), ['document_type' => 'FT', 'client_id' => $this->cliente->id, 'idempotency_key' => $key], $this->tenant->id, $this->user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('restaurant', $first->source_module);
        $this->assertEquals(2, (float) $item->fresh()->billed_quantity);
        $this->assertSame('billed', $order->fresh()->status);
        $this->assertSame('cleaning', $this->table->fresh()->status);
        $this->assertDatabaseCount('restaurant_order_item_billings', 1);
        $this->service()->releaseTable($order->fresh(), $this->tenant->id, $this->user->id);
        $this->assertSame('available', $this->table->fresh()->status);
    }

    public function test_checkout_fr_cria_entrada_na_tesouraria_sem_recibo_duplicado(): void
    {
        $product = $this->produtoComStock(10, 1000);
        $order = $this->service()->open(['venue_id' => $this->venue->id, 'table_id' => $this->table->id], $this->tenant->id, $this->user->id);
        $this->service()->addItem($order, $product->id, 1, null, $this->tenant->id, $this->user->id);
        $order->update(['status' => 'served']);
        $method = \App\Models\Treasury\PaymentMethod::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'TPA Teste', 'code' => 'TPATEST'.uniqid(), 'type' => 'card', 'is_active' => true,
        ]);
        $invoice = app(RestaurantCheckoutService::class)->checkout($order, [
            'document_type' => 'FR', 'client_id' => $this->cliente->id,
            'payment_method_id' => $method->id, 'idempotency_key' => (string) Str::uuid(),
        ], $this->tenant->id, $this->user->id);

        $this->assertSame('paid', $invoice->status);
        $this->assertDatabaseHas('treasury_transactions', ['tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id, 'amount' => $invoice->total]);
        $this->assertDatabaseMissing('invoicing_receipts', ['tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id]);
    }

    public function test_reserva_bloqueia_conflito_e_liberta_mesa_no_cancelamento(): void
    {
        $service = app(RestaurantReservationService::class);
        $data = ['venue_id' => $this->venue->id, 'table_id' => $this->table->id, 'guest_name' => 'Família Teste', 'guest_count' => 4, 'reserved_at' => now()->addDay()->setTime(19, 0), 'duration_minutes' => 120, 'status' => 'pending'];
        $reservation = $service->save($data, $this->tenant->id, $this->user->id);
        $service->changeStatus($reservation, 'confirmed', $this->tenant->id);
        $this->assertSame('reserved', $this->table->fresh()->status);

        try {
            $service->save($data + ['guest_name' => 'Conflito'], $this->tenant->id, $this->user->id);
            $this->fail('Uma segunda reserva sobreposta deveria ser recusada.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('restaurant_reservations', 1);
        }
        $service->changeStatus($reservation->fresh(), 'cancelled', $this->tenant->id);
        $this->assertSame('available', $this->table->fresh()->status);
    }

    public function test_divisao_por_itens_e_pagamento_misto_nao_duplica_quantidades(): void
    {
        $p1=$this->produtoComStock(10,1000);$p2=$this->produtoComStock(10,2000);
        $order=$this->service()->open(['venue_id'=>$this->venue->id,'table_id'=>$this->table->id],$this->tenant->id,$this->user->id);
        $i1=$this->service()->addItem($order,$p1->id,1,null,$this->tenant->id,$this->user->id);$i2=$this->service()->addItem($order,$p2->id,1,null,$this->tenant->id,$this->user->id);$order->update(['status'=>'served']);
        $checkout=app(RestaurantCheckoutService::class);$checkout->checkout($order,['document_type'=>'FT','client_id'=>$this->cliente->id,'item_ids'=>[$i1->id],'idempotency_key'=>(string)Str::uuid()],$this->tenant->id,$this->user->id);
        $this->assertSame('partially_billed',$order->fresh()->status);$this->assertEquals(1,(float)$i1->fresh()->billed_quantity);$this->assertEquals(0,(float)$i2->fresh()->billed_quantity);
        $m1=\App\Models\Treasury\PaymentMethod::withoutGlobalScopes()->create(['tenant_id'=>$this->tenant->id,'name'=>'TPA A','code'=>'TPAA'.uniqid(),'type'=>'card','is_active'=>true]);$m2=\App\Models\Treasury\PaymentMethod::withoutGlobalScopes()->create(['tenant_id'=>$this->tenant->id,'name'=>'TPA B','code'=>'TPAB'.uniqid(),'type'=>'card','is_active'=>true]);$total=(float)$i2->line_total;
        $invoice=$checkout->checkout($order->fresh(),['document_type'=>'FR','client_id'=>$this->cliente->id,'item_ids'=>[$i2->id],'payments'=>[['payment_method_id'=>$m1->id,'amount'=>$total/2],['payment_method_id'=>$m2->id,'amount'=>$total/2]],'idempotency_key'=>(string)Str::uuid()],$this->tenant->id,$this->user->id);
        $this->assertSame('billed',$order->fresh()->status);$this->assertEquals($invoice->total,\App\Models\Treasury\Transaction::withoutGlobalScopes()->where('invoice_id',$invoice->id)->sum('amount'));$this->assertSame(2,\App\Models\Treasury\Transaction::withoutGlobalScopes()->where('invoice_id',$invoice->id)->count());
    }

    public function test_transfere_comanda_apenas_para_mesa_livre_do_mesmo_estabelecimento(): void
    {
        $target = DiningTable::withoutGlobalScopes()->create(['tenant_id'=>$this->tenant->id,'venue_id'=>$this->venue->id,'code'=>'M99','name'=>'Mesa 99','status'=>'available']);
        $order = $this->service()->open(['venue_id'=>$this->venue->id,'table_id'=>$this->table->id],$this->tenant->id,$this->user->id);
        $this->service()->transfer($order,$target->id,$this->tenant->id,$this->user->id);
        $this->assertSame($target->id,$order->fresh()->table_id);
        $this->assertSame('available',$this->table->fresh()->status);
        $this->assertSame('occupied',$target->fresh()->status);
        $this->assertDatabaseHas('restaurant_order_events',['order_id'=>$order->id,'event'=>'table_transferred']);
    }

    public function test_junta_comandas_e_liberta_mesa_de_origem(): void
    {
        $secondTable = DiningTable::withoutGlobalScopes()->create(['tenant_id'=>$this->tenant->id,'venue_id'=>$this->venue->id,'code'=>'M98','name'=>'Mesa 98','status'=>'available']);
        $product = $this->produtoComStock(10,1000);
        $source = $this->service()->open(['venue_id'=>$this->venue->id,'table_id'=>$this->table->id],$this->tenant->id,$this->user->id);
        $target = $this->service()->open(['venue_id'=>$this->venue->id,'table_id'=>$secondTable->id],$this->tenant->id,$this->user->id);
        $item = $this->service()->addItem($source,$product->id,1,null,$this->tenant->id,$this->user->id);
        $this->service()->merge($source,$target,$this->tenant->id,$this->user->id);
        $this->assertSame($target->id,$item->fresh()->order_id);
        $this->assertSame('cancelled',$source->fresh()->status);
        $this->assertSame('available',$this->table->fresh()->status);
        $this->assertGreaterThan(0,(float)$target->fresh()->grand_total);
    }

    public function test_anulacao_pos_producao_regista_desperdicio_e_retira_dos_totais(): void
    {
        $product = $this->produtoComStock(10,1000);
        $order = $this->service()->open(['venue_id'=>$this->venue->id,'table_id'=>$this->table->id],$this->tenant->id,$this->user->id);
        $item = $this->service()->addItem($order,$product->id,1,null,$this->tenant->id,$this->user->id);
        $this->service()->confirm($order,$this->tenant->id,$this->user->id);
        $this->service()->voidProducedItem($item->fresh(),'Cliente cancelou',$this->tenant->id,$this->user->id);
        $this->assertSame('voided',$item->fresh()->kitchen_status);
        $this->assertDatabaseHas('restaurant_wastes',['tenant_id'=>$this->tenant->id,'order_item_id'=>$item->id,'reason'=>'Cliente cancelou']);
        $this->assertEquals(0,(float)$order->fresh()->grand_total);
    }
}
