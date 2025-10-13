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
