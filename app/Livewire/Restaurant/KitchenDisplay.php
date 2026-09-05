<?php

namespace App\Livewire\Restaurant;

use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\KitchenTicket;
use App\Services\Restaurant\RestaurantKitchenService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Cozinha - Restaurante')]
class KitchenDisplay extends Component
{
    public ?int $stationId = null;

    public function advance(int $ticketId): void
    {
        $tenantId = activeTenantId();
        $ticket = KitchenTicket::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($ticketId);
        $next = ['queued' => 'accepted', 'accepted' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'][$ticket->status] ?? null;
        if (!$next) return;
        app(RestaurantKitchenService::class)->transition($ticket, $next, $tenantId, auth()->id());
        session()->flash('success', 'Ticket atualizado com sucesso.');
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $stations = KitchenStation::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->get();
        $tickets = KitchenTicket::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->when($this->stationId, fn ($q) => $q->where('station_id', $this->stationId))
            ->whereIn('status', ['queued', 'accepted', 'preparing', 'ready'])
            ->with(['station', 'order.table', 'items.orderItem'])->orderByDesc('priority')->orderBy('queued_at')
            ->limit(50)->get(); // teto de segurança: o poll de 15s não pode arrastar centenas de bilhetes
        // O valor de arranque da impressao automatica; cada aparelho decide o
        // seu por cima (localStorage), porque a impressora esta num posto so.
        $autoPrint = (bool) (\App\Models\Restaurant\RestaurantSettings::forTenant($tenantId)->kitchen_auto_print ?? false);

        return view('livewire.restaurant.kitchen-display', compact('stations', 'tickets', 'autoPrint'));
    }
}
