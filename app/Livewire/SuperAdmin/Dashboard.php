<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $tenant = Tenant::find($tenantId);
        if ($tenant) {
            session(['impersonate_tenant_id' => $tenant->id]);
            return redirect('/dashboard');
        }
    }
    
    public function deleteTenant($tenantId)
    {
        try {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $tenant->delete();
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Tenant eliminado com sucesso!'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao eliminar tenant: ' . $e->getMessage()
            ]);
        }
    }

    private function getStats(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $inactiveTenants = $totalTenants - $activeTenants;
        $totalUsers = User::where('is_super_admin', false)->count();
        $totalModules = Module::where('is_active', true)->count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $trialSubscriptions = Subscription::where('status', 'trial')->count();

        $totalRevenue = 0;
        $monthlyRevenue = 0;
        $pendingRevenue = 0;
        try {
            $totalRevenue = Invoice::where('status', 'paid')->sum('total');
            $monthlyRevenue = Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total');
            $pendingRevenue = Invoice::where('status', 'pending')->sum('total');
        } catch (\Exception $e) {}

        $pendingOrders = 0;
        try {
            $pendingOrders = Order::where('status', 'pending')->count();
        } catch (\Exception $e) {}

        $newTenantsThisMonth = Tenant::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $newTenantsLastMonth = Tenant::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)->count();
        $tenantGrowth = $newTenantsLastMonth > 0 
            ? round((($newTenantsThisMonth - $newTenantsLastMonth) / $newTenantsLastMonth) * 100, 1) 
            : ($newTenantsThisMonth > 0 ? 100 : 0);

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'inactive_tenants' => $inactiveTenants,
            'total_users' => $totalUsers,
            'total_modules' => $totalModules,
            'active_subscriptions' => $activeSubscriptions,
            'trial_subscriptions' => $trialSubscriptions,
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'pending_revenue' => $pendingRevenue,
            'pending_orders' => $pendingOrders,
            'new_tenants_month' => $newTenantsThisMonth,
            'tenant_growth' => $tenantGrowth,
        ];
    }

    private function getPlansDistribution(): array
    {
        try {
            return Plan::where('is_active', true)
                ->withCount(['subscriptions' => function ($q) {
                    $q->where('status', 'active');
                }])
                ->orderByDesc('subscriptions_count')
                ->get()
                ->map(fn($plan) => [
                    'name' => $plan->name,
                    'count' => $plan->subscriptions_count,
                    'price' => $plan->price_monthly,
                    'color' => $this->getPlanColor($plan->order ?? 0),
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTopModules(): array
    {
        try {
            return Module::where('is_active', true)
                ->withCount(['tenants' => function ($q) {
                    $q->wherePivot('is_active', true);
                }])
                ->orderByDesc('tenants_count')
                ->take(10)
                ->get()
                ->map(fn($mod) => [
                    'name' => $mod->name,
                    'icon' => $mod->icon ?? 'fas fa-puzzle-piece',
                    'count' => $mod->tenants_count,
                    'is_core' => $mod->is_core,
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getExpiringSubscriptions()
    {
        try {
            return Subscription::with(['tenant', 'plan'])
                ->where('status', 'active')
                ->whereNotNull('current_period_end')
                ->where('current_period_end', '<=', now()->addDays(30))
                ->where('current_period_end', '>=', now())
                ->orderBy('current_period_end')
                ->take(10)
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function getTopSubscriptions()
    {
        try {
            return Subscription::with(['tenant', 'plan'])
                ->where('status', 'active')
                ->orderByDesc('amount')
                ->take(5)
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function getRecentOrders()
    {
        try {
            return Order::with(['tenant', 'plan', 'user'])
                ->latest()
                ->take(5)
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function getTenantGrowthChart(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Tenant::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $months[] = [
                'label' => $date->translatedFormat('M/y'),
                'count' => $count,
            ];
        }
        return $months;
    }

    private function getRevenueChart(): array
    {
        $months = [];
        try {
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $revenue = Invoice::where('status', 'paid')
                    ->whereYear('paid_at', $date->year)
                    ->whereMonth('paid_at', $date->month)
                    ->sum('total');
                $months[] = [
                    'label' => $date->translatedFormat('M/y'),
                    'amount' => round($revenue, 2),
                ];
            }
        } catch (\Exception $e) {
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $months[] = ['label' => $date->translatedFormat('M/y'), 'amount' => 0];
            }
        }
        return $months;
    }

    private function getPlanColor(int $index): string
    {
        $colors = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#6366f1', '#14b8a6'];
        return $colors[$index % count($colors)];
    }

    public function render()
    {
        $stats = $this->getStats();
        $plansDistribution = $this->getPlansDistribution();
        $topModules = $this->getTopModules();
        $expiringSubscriptions = $this->getExpiringSubscriptions();
        $topSubscriptions = $this->getTopSubscriptions();
        $recentOrders = $this->getRecentOrders();
        $tenantGrowthChart = $this->getTenantGrowthChart();
        $revenueChart = $this->getRevenueChart();

        $recentTenants = Tenant::with(['activeSubscription.plan', 'modules'])
            ->withCount('users')
            ->latest()
            ->take(5)
            ->get();

        $recentInvoices = collect();
        try {
            $recentInvoices = Invoice::with('tenant')->latest()->take(5)->get();
        } catch (\Exception $e) {}

        return view('livewire.super-admin.dashboard.dashboard', compact(
            'stats',
            'plansDistribution',
            'topModules',
            'expiringSubscriptions',
            'topSubscriptions',
            'recentOrders',
            'tenantGrowthChart',
            'revenueChart',
            'recentTenants',
            'recentInvoices'
        ));
    }
}
