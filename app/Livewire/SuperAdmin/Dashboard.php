<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Dashboard - Super Admin')]
class Dashboard extends Component
{
    public function viewTenant($tenantId)
    {
        return redirect()->route('superadmin.tenants.show', $tenantId);
    }
    
    public function editTenant($tenantId)
    {
        return redirect()->route('superadmin.tenants.edit', $tenantId);
    }
    
    public function manageTenant($tenantId)
    {
        // Impersonar o tenant
        $tenant = \App\Models\Tenant::find($tenantId);
        if ($tenant) {
            session(['impersonate_tenant_id' => $tenant->id]);
            return redirect('/dashboard');
        }
    }
    
    public function deleteTenant($tenantId)
    {
        try {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                $tenant->delete();
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => '✅ Tenant eliminado com sucesso!'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Erro ao eliminar tenant: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        $totalRevenue = 0;
        $recentInvoices = collect();
        try {
            $totalRevenue = \App\Models\Invoice::where('status', 'paid')->sum('total');
            $recentInvoices = \App\Models\Invoice::with('tenant')->latest()->take(5)->get();
        } catch (\Exception $e) {
            // Invoice table may not exist yet
        }

        $stats = [
            'total_tenants' => \App\Models\Tenant::count(),
            'active_tenants' => \App\Models\Tenant::where('is_active', true)->count(),
            'total_users' => \App\Models\User::where('is_super_admin', false)->count(),
            'total_revenue' => $totalRevenue,
            'total_modules' => \App\Models\Module::where('is_active', true)->count(),
            'active_subscriptions' => \App\Models\Subscription::where('status', 'active')->count(),
        ];

        $recentTenants = \App\Models\Tenant::with(['activeSubscription.plan', 'modules'])
            ->withCount('users')
            ->latest()
            ->take(5)
            ->get();

        return view('livewire.super-admin.dashboard.dashboard', compact('stats', 'recentTenants', 'recentInvoices'));
    }
}
