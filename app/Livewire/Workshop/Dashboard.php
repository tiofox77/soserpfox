<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\Service;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public $dateFrom;
    public $dateTo;
    
    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        // KPIs Principais
        $totalOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('received_at', [$this->dateFrom, $this->dateTo])
            ->count();
            
        $pendingOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->count();
            
        $inProgressOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', 'in_progress')
            ->count();
            
        $completedOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$this->dateFrom, $this->dateTo])
            ->count();
            
        $totalRevenue = WorkOrder::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('received_at', [$this->dateFrom, $this->dateTo])
            ->sum('total');
            
        $pendingPayments = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['completed', 'delivered'])
            ->where('payment_status', '!=', 'paid')
            ->sum('total');
            
        // Veículos
        $totalVehicles = Vehicle::where('tenant_id', $tenantId)->count();
        $activeVehicles = Vehicle::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
            
        // Serviços mais utilizados
        $topServices = DB::table('workshop_work_order_items')
            ->join('workshop_work_orders', 'workshop_work_order_items.work_order_id', '=', 'workshop_work_orders.id')
            ->join('workshop_services', 'workshop_work_order_items.service_id', '=', 'workshop_services.id')
            ->where('workshop_work_orders.tenant_id', $tenantId)
            ->where('workshop_work_order_items.type', 'service')
            ->whereBetween('workshop_work_orders.received_at', [$this->dateFrom, $this->dateTo])
            ->select('workshop_services.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(workshop_work_order_items.subtotal) as revenue'))
            ->groupBy('workshop_services.id', 'workshop_services.name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
            
        // OS por status
        $ordersByStatus = WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('received_at', [$this->dateFrom, $this->dateTo])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
            
        // OS urgentes
        $urgentOrders = WorkOrder::with(['vehicle', 'mechanic'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'scheduled', 'in_progress'])
            ->where('priority', 'urgent')
            ->orderBy('received_at', 'desc')
            ->limit(5)
            ->get();
            
        // Documentos vencendo
        $expiringDocuments = Vehicle::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function($query) {
                $query->where('registration_expiry', '<=', now()->addDays(30))
                      ->orWhere('insurance_expiry', '<=', now()->addDays(30))
                      ->orWhere('inspection_expiry', '<=', now()->addDays(30));
            })
            ->limit(5)
            ->get();

        return view('livewire.workshop.dashboard.dashboard', [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'inProgressOrders' => $inProgressOrders,
            'completedOrders' => $completedOrders,
            'totalRevenue' => $totalRevenue,
            'pendingPayments' => $pendingPayments,
            'totalVehicles' => $totalVehicles,
            'activeVehicles' => $activeVehicles,
            'topServices' => $topServices,
            'ordersByStatus' => $ordersByStatus,
            'urgentOrders' => $urgentOrders,
            'expiringDocuments' => $expiringDocuments,
        ]);
    }
}
