<?php

namespace App\Services\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderEvent;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Services\Invoicing\TaxResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RestaurantOrderService
{
    public function open(array $data, int $tenantId, ?int $userId): Order
    {
        return DB::transaction(function () use ($data, $tenantId, $userId) {
            $venue = Venue::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->find($data['venue_id'] ?? null);
            if (!$venue) {
                throw new InvalidArgumentException('Estabelecimento inválido para esta empresa.');
            }

            // Idempotência vem antes da validação da mesa: num retry legítimo a
            // mesa já está ocupada precisamente pela comanda criada na 1.ª chamada.
            $localUuid = $data['local_uuid'] ?? (string) Str::uuid();
            $existing = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('local_uuid', $localUuid)
                ->first();
            if ($existing) {
                return $existing;
            }

            $table = null;
            if (!empty($data['table_id'])) {
                $table = DiningTable::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('venue_id', $venue->id)
                    ->lockForUpdate()
                    ->find($data['table_id']);
                if (!$table || !$table->is_active || in_array($table->status, ['blocked', 'cleaning'], true)) {
                    throw new InvalidArgumentException('A mesa não está disponível para atendimento.');
                }
                $hasOpenOrder = Order::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('table_id', $table->id)
                    ->whereIn('status', Order::OPEN_STATUSES)
                    ->exists();
                if ($hasOpenOrder) {
                    throw new InvalidArgumentException('A mesa já possui uma comanda aberta.');
                }
            }

            $settings = RestaurantSettings::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();
            if (!$settings) {
                RestaurantSettings::forTenant($tenantId);
                $settings = RestaurantSettings::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            $number = (int) $settings->next_order_number;
            $settings->update(['next_order_number' => $number + 1]);

            $order = Order::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'venue_id' => $venue->id,
                'table_id' => $table?->id,
                'client_id' => $data['client_id'] ?? null,
                'waiter_id' => $userId,
                'order_number' => sprintf('CMD-%s-%06d', now()->format('Y'), $number),
                'local_uuid' => $localUuid,
                'channel' => $table ? 'table' : ($data['channel'] ?? 'counter'),
                'status' => 'draft',
                'guest_count' => max(1, (int) ($data['guest_count'] ?? 1)),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($table) {
                $table->update(['status' => 'occupied']);
            }
            $this->event($order, 'order_opened', null, ['channel' => $order->channel], $userId);

            return $order->fresh(['table', 'venue', 'items']);
        });
    }

    public function addItem(Order $order, int $productId, float $quantity, ?string $notes, int $tenantId, ?int $userId): OrderItem
    {
        if ($order->tenant_id !== $tenantId || !in_array($order->status, ['draft', 'confirmed'], true)) {
            throw new InvalidArgumentException('A comanda já não permite adicionar artigos.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A quantidade deve ser superior a zero.');
        }

        $product = Product::where('tenant_id', $tenantId)->where('is_active', true)->find($productId);
        if (!$product) {
            throw new InvalidArgumentException('Artigo inválido para esta empresa.');
        }

        return DB::transaction(function () use ($order, $product, $quantity, $notes, $tenantId, $userId) {
            $tax = TaxResolver::forProductId($product->id, $tenantId);
            $unitPrice = (float) $product->price;
            $base = round($quantity * $unitPrice, 2);
            $taxAmount = round($base * ((float) $tax['rate'] / 100), 2);

            $item = OrderItem::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit' => $product->unit ?: 'UN',
                'unit_price' => $unitPrice,
                'tax_rate' => $tax['rate'],
                'tax_amount' => $taxAmount,
                'line_total' => $base + $taxAmount,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->recalculate($order, $tenantId);
            $this->event($order, 'item_added', $item, [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ], $userId);

            return $item;
        });
    }

    public function removeItem(OrderItem $item, int $tenantId, ?int $userId, string $reason): void
    {
        $order = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($item->order_id);
        if ($item->tenant_id !== $tenantId || $item->kitchen_status !== 'draft' || $order->status !== 'draft') {
            throw new InvalidArgumentException('O artigo já foi confirmado. Registe um cancelamento/desperdício em vez de o apagar.');
        }

        DB::transaction(function () use ($item, $order, $tenantId, $userId, $reason) {
            $snapshot = $item->only(['id', 'product_id', 'product_name', 'quantity', 'line_total']);
            $item->delete();
            $this->recalculate($order, $tenantId);
            $this->event($order, 'item_removed', null, $snapshot + ['reason' => $reason], $userId);
        });
    }

    public function confirm(Order $order, int $tenantId, ?int $userId): Order
    {
        if ($order->tenant_id !== $tenantId || $order->status !== 'draft') {
            throw new InvalidArgumentException('Apenas comandas em rascunho podem ser confirmadas.');
        }
        if (!$order->items()->exists()) {
            throw new InvalidArgumentException('Adicione pelo menos um artigo antes de confirmar.');
        }

        return DB::transaction(function () use ($order, $tenantId, $userId) {
            $order->items()->where('kitchen_status', 'draft')->update(['kitchen_status' => 'queued']);
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            if ($order->table_id) {
                DiningTable::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($order->table_id)
                    ->update(['status' => 'waiting_kitchen']);
            }
            $this->event($order, 'order_confirmed', null, [], $userId);
            $order = $order->fresh(['items', 'table', 'venue']);
            app(RestaurantKitchenService::class)->dispatch($order, $tenantId, $userId);
            return $order->fresh(['items', 'table', 'venue']);
        });
    }

    public function releaseTable(Order $order, int $tenantId, ?int $userId): void
    {
        if ($order->tenant_id !== $tenantId || $order->status !== 'billed' || !$order->table_id) {
            throw new InvalidArgumentException('A mesa só pode ser libertada depois do fecho integral da comanda.');
        }
        DB::transaction(function () use ($order, $tenantId, $userId) {
            $hasPending = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('table_id', $order->table_id)
                ->where('id', '!=', $order->id)->whereIn('status', Order::OPEN_STATUSES)->lockForUpdate()->exists();
            if ($hasPending) throw new InvalidArgumentException('A mesa ainda possui outra comanda pendente.');
            DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($order->table_id)->update(['status' => 'available']);
            $this->event($order, 'table_released', null, ['table_id' => $order->table_id], $userId);
        });
    }

    public function transfer(Order $order,int $targetTableId,int $tenantId,?int $userId):Order{return DB::transaction(function()use($order,$targetTableId,$tenantId,$userId){$order=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->whereIn('status',Order::OPEN_STATUSES)->lockForUpdate()->findOrFail($order->id);$target=DiningTable::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('venue_id',$order->venue_id)->where('status','available')->lockForUpdate()->find($targetTableId);if(!$target)throw new InvalidArgumentException('A mesa de destino não está livre.');$old=DiningTable::withoutGlobalScopes()->where('tenant_id',$tenantId)->lockForUpdate()->find($order->table_id);$order->update(['table_id'=>$target->id]);$target->update(['status'=>$old?->status??'occupied']);if($old)$old->update(['status'=>'available']);$this->event($order,'table_transferred',null,['from'=>$old?->id,'to'=>$target->id],$userId);return $order->fresh(['table','items']);});}

    public function merge(Order $source,Order $target,int $tenantId,?int $userId):Order{return DB::transaction(function()use($source,$target,$tenantId,$userId){$source=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->lockForUpdate()->findOrFail($source->id);$target=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->lockForUpdate()->findOrFail($target->id);if($source->id===$target->id||$source->venue_id!==$target->venue_id||!in_array($source->status,Order::OPEN_STATUSES,true)||!in_array($target->status,Order::OPEN_STATUSES,true))throw new InvalidArgumentException('Comandas inválidas para junção.');if($source->items()->where('billed_quantity','>',0)->exists())throw new InvalidArgumentException('Não é possível juntar uma comanda parcialmente faturada.');OrderItem::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('order_id',$source->id)->update(['order_id'=>$target->id]);$sourceTable=$source->table_id;$source->update(['status'=>'cancelled','closed_at'=>now(),'closed_by'=>$userId,'notes'=>trim(($source->notes??'').' Juntada a '.$target->order_number)]);if($sourceTable)DiningTable::withoutGlobalScopes()->where('tenant_id',$tenantId)->whereKey($sourceTable)->update(['status'=>'available']);$this->recalculate($target,$tenantId);$this->event($target,'orders_merged',null,['source_order_id'=>$source->id],$userId);return $target->fresh(['table','items']);});}

    public function voidProducedItem(OrderItem $item,string $reason,int $tenantId,?int $userId):void{if($item->tenant_id!==$tenantId||!in_array($item->kitchen_status,['queued','accepted','preparing','ready'],true))throw new InvalidArgumentException('Este artigo não pode ser anulado por este fluxo.');if(trim($reason)==='')throw new InvalidArgumentException('O motivo é obrigatório.');DB::transaction(function()use($item,$reason,$tenantId,$userId){$item->update(['kitchen_status'=>'voided']);\App\Models\Restaurant\KitchenTicketItem::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('order_item_id',$item->id)->update(['status'=>'voided']);\App\Models\Restaurant\Waste::withoutGlobalScopes()->create(['tenant_id'=>$tenantId,'order_id'=>$item->order_id,'order_item_id'=>$item->id,'quantity'=>$item->quantity,'reason'=>$reason,'user_id'=>$userId]);$order=Order::withoutGlobalScopes()->where('tenant_id',$tenantId)->findOrFail($item->order_id);$this->recalculate($order,$tenantId);$this->event($order,'produced_item_voided',$item,['reason'=>$reason,'quantity'=>$item->quantity],$userId);});}

    protected function recalculate(Order $order, int $tenantId): void
    {
        $totals = OrderItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->where('kitchen_status', '!=', 'voided')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) subtotal')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) discount_total')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) tax_total')
            ->selectRaw('COALESCE(SUM(line_total), 0) grand_total')
            ->first();

        $order->update([
            'subtotal' => $totals->subtotal,
            'discount_total' => $totals->discount_total,
            'tax_total' => $totals->tax_total,
            'grand_total' => $totals->grand_total,
        ]);
    }

    protected function event(Order $order, string $event, ?OrderItem $item, array $payload, ?int $userId): void
    {
        OrderEvent::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'user_id' => $userId,
            'event' => $event,
            'payload' => $payload,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
