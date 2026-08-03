<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\KitchenTicket;
use App\Models\Restaurant\KitchenTicketItem;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderEvent;
use App\Models\Restaurant\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestaurantKitchenService
{
    public function dispatch(Order $order, int $tenantId, ?int $userId): KitchenTicket
    {
        if ($order->tenant_id !== $tenantId || !in_array($order->status, ['confirmed', 'in_preparation'], true)) {
            throw new InvalidArgumentException('A comanda não está pronta para envio à cozinha.');
        }

        return DB::transaction(function () use ($order, $tenantId, $userId) {
            $station = KitchenStation::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'venue_id' => $order->venue_id, 'code' => 'COZINHA'],
                ['name' => 'Cozinha', 'sort_order' => 1, 'is_active' => true]
            );

            $items = OrderItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('order_id', $order->id)
                ->where('kitchen_status', 'queued')->lockForUpdate()->get();

            $existing = KitchenTicket::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('order_id', $order->id)
                ->whereIn('status', ['queued', 'accepted', 'preparing'])->latest('id')->first();
            if ($items->isEmpty() && $existing) return $existing;
            if ($items->isEmpty()) throw new InvalidArgumentException('Não existem artigos novos para enviar à cozinha.');

            $sequence = KitchenTicket::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->count() + 1;
            $ticket = KitchenTicket::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'venue_id' => $order->venue_id, 'station_id' => $station->id,
                'order_id' => $order->id, 'ticket_number' => sprintf('KDS-%s-%06d', now()->format('Y'), $sequence),
                'status' => 'queued', 'queued_at' => now(),
            ]);
            foreach ($items as $item) {
                KitchenTicketItem::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId, 'ticket_id' => $ticket->id, 'order_item_id' => $item->id, 'status' => 'queued',
                ]);
            }
            $order->update(['status' => 'in_preparation']);
            app(RestaurantStockService::class)->consume($order->fresh('venue'), $tenantId, $userId);
            OrderEvent::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'order_id' => $order->id, 'user_id' => $userId,
                'event' => 'kitchen_ticket_created', 'payload' => ['ticket_id' => $ticket->id, 'items' => $items->pluck('id')->all()],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);
            return $ticket->fresh(['items.orderItem', 'order.table', 'station']);
        });
    }

    public function transition(KitchenTicket $ticket, string $status, int $tenantId, ?int $userId): KitchenTicket
    {
        $allowed = [
            'queued' => ['accepted'], 'accepted' => ['preparing'], 'preparing' => ['ready'], 'ready' => ['served'],
        ];
        if ($ticket->tenant_id !== $tenantId || !in_array($status, $allowed[$ticket->status] ?? [], true)) {
            throw new InvalidArgumentException('Transição de cozinha inválida.');
        }

        return DB::transaction(function () use ($ticket, $status, $tenantId, $userId) {
            $ticket = KitchenTicket::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($ticket->id);
            $times = match ($status) {
                'accepted' => ['accepted_at' => now()], 'ready' => ['ready_at' => now()],
                'served' => ['served_at' => now()], default => [],
            };
            $ticket->update(['status' => $status] + $times);
            $ticket->items()->update(['status' => $status]);
            OrderItem::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->whereIn('id', $ticket->items()->pluck('order_item_id'))->update(['kitchen_status' => $status]);

            $order = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($ticket->order_id);
            $allStatuses = OrderItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_id', $order->id)->pluck('kitchen_status');
            if ($allStatuses->every(fn ($value) => $value === 'served')) {
                $order->update(['status' => 'served']);
                if ($order->table_id) DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($order->table_id)->update(['status' => 'served']);
            } elseif ($allStatuses->every(fn ($value) => in_array($value, ['ready', 'served'], true))) {
                $order->update(['status' => 'ready']);
            }
            OrderEvent::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'order_id' => $order->id, 'user_id' => $userId,
                'event' => 'kitchen_'.$status, 'payload' => ['ticket_id' => $ticket->id],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);
            return $ticket->fresh(['items.orderItem', 'order.table', 'station']);
        });
    }
}
