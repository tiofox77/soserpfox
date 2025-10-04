<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Dashboard - Super Admin')]
class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'total_tenants' => \App\Models\Tenant::count(),
            'active_tenants' => \App\Models\Tenant::where('is_active', true)->count(),
            'total_users' => \App\Models\User::count(),
            'total_revenue' => \App\Models\Invoice::where('status', 'paid')->sum('total'),
        ];

        $recentTenants = \App\Models\Tenant::latest()->take(5)->get();
        $recentInvoices = \App\Models\Invoice::with('tenant')->latest()->take(5)->get();

        return view('livewire.super-admin.dashboard.dashboard', compact('stats', 'recentTenants', 'recentInvoices'));
    }
}
