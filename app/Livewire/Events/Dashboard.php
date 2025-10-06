<?php

namespace App\Livewire\Events;

use App\Models\Events\Event;
use App\Models\Events\Equipment;
use Livewire\Component;
use Carbon\Carbon;

class Dashboard extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();

        $stats = [
            'events_month' => Event::where('tenant_id', $tenantId)
                ->whereMonth('start_date', Carbon::now()->month)
                ->count(),
            'confirmed_events' => Event::where('tenant_id', $tenantId)
                ->where('status', 'confirmado')
                ->count(),
            'in_progress' => Event::where('tenant_id', $tenantId)
                ->whereIn('status', ['em_montagem', 'em_andamento'])
                ->count(),
            'equipment_in_use' => Equipment::where('tenant_id', $tenantId)
                ->where('status', 'em_uso')
                ->count(),
        ];

        $upcomingEvents = Event::where('tenant_id', $tenantId)
            ->where('start_date', '>=', Carbon::now())
            ->whereIn('status', ['confirmado', 'em_montagem'])
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->with(['client', 'venue'])
            ->get();

        return view('livewire.events.dashboard', compact('stats', 'upcomingEvents'));
    }
}
