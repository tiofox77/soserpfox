<?php

namespace App\Livewire\Restaurant;

use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard - Restaurante')]
class Dashboard extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();

        return view('livewire.restaurant.dashboard', [
            'tablesTotal' => DiningTable::where('is_active', true)->count(),
            'tablesOccupied' => DiningTable::whereIn('status', ['occupied', 'waiting_kitchen', 'served', 'billing'])->count(),
            'openOrders' => Order::open()->count(),
            'todaySales' => Order::whereDate('created_at', today())
                ->whereNotIn('status', ['cancelled'])
                ->sum('grand_total'),
            'recentOrders' => Order::with(['table', 'waiter'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(8)
                ->get(),
            'statusCounts' => DiningTable::where('is_active', true)
                ->selectRaw('status, COUNT(*) total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }
}
