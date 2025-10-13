<?php

namespace App\Livewire\Workshop;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\Service;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Reports extends Component
{
    public $reportType = 'services';
    public $dateFrom;
    public $dateTo;
    public $statusFilter = '';
    
    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        $data = [];

        switch ($this->reportType) {
            case 'services':
                $data = $this->getServicesReport($tenantId);
                break;
            case 'revenue':
                $data = $this->getRevenueReport($tenantId);
                break;
            case 'vehicles':
                $data = $this->getVehiclesReport($tenantId);
                break;
            case 'mechanics':
                $data = $this->getMechanicsReport($tenantId);
                break;
            case 'workorders':
                $data = $this->getWorkOrdersReport($tenantId);
                break;
        }

        return view('livewire.workshop.reports.reports', [
            'reportData' => $data,
        ]);
    }

    private function getServicesReport($tenantId)
    {
        return DB::table('workshop_work_order_items')
            ->join('workshop_work_orders', 'workshop_work_order_items.work_order_id', '=', 'workshop_work_orders.id')
            ->leftJoin('workshop_services', 'workshop_work_order_items.service_id', '=', 'workshop_services.id')
            ->where('workshop_work_orders.tenant_id', $tenantId)
            ->where('workshop_work_order_items.type', 'service')
            ->whereBetween('workshop_work_orders.received_at', [$this->dateFrom, $this->dateTo])
            ->select(
                'workshop_work_order_items.name',
                DB::raw('COUNT(*) as total_uses'),
                DB::raw('SUM(workshop_work_order_items.quantity) as total_quantity'),
                DB::raw('SUM(workshop_work_order_items.subtotal) as total_revenue'),
                DB::raw('AVG(workshop_work_order_items.unit_price) as avg_price')
            )
            ->groupBy('workshop_work_order_items.name')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    private function getRevenueReport($tenantId)
    {
        return WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('received_at', [$this->dateFrom, $this->dateTo])
            ->select(
                DB::raw('DATE(received_at) as date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(labor_total) as labor_revenue'),
                DB::raw('SUM(parts_total) as parts_revenue'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('SUM(CASE WHEN payment_status = "paid" THEN total ELSE 0 END) as paid_amount'),
                DB::raw('SUM(CASE WHEN payment_status != "paid" THEN total ELSE 0 END) as pending_amount')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
    }

    private function getVehiclesReport($tenantId)
    {
        return Vehicle::where('workshop_vehicles.tenant_id', $tenantId)
            ->leftJoin('workshop_work_orders', 'workshop_vehicles.id', '=', 'workshop_work_orders.vehicle_id')
            ->select(
                'workshop_vehicles.id',
                'workshop_vehicles.plate',
                'workshop_vehicles.brand',
                'workshop_vehicles.model',
                'workshop_vehicles.owner_name',
                DB::raw('COUNT(workshop_work_orders.id) as total_orders'),
                DB::raw('SUM(CASE WHEN workshop_work_orders.received_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as period_orders'),
                DB::raw('SUM(CASE WHEN workshop_work_orders.received_at BETWEEN ? AND ? THEN workshop_work_orders.total ELSE 0 END) as period_revenue')
            )
            ->setBindings([$this->dateFrom, $this->dateTo, $this->dateFrom, $this->dateTo])
            ->groupBy('workshop_vehicles.id', 'workshop_vehicles.plate', 'workshop_vehicles.brand', 'workshop_vehicles.model', 'workshop_vehicles.owner_name')
            ->orderBy('period_revenue', 'desc')
            ->get();
    }

    private function getMechanicsReport($tenantId)
    {
        return DB::table('hr_employees')
            ->leftJoin('workshop_work_orders', function($join) {
                $join->on('hr_employees.id', '=', 'workshop_work_orders.mechanic_id')
                     ->whereBetween('workshop_work_orders.received_at', [$this->dateFrom, $this->dateTo]);
            })
            ->where('hr_employees.tenant_id', $tenantId)
            ->select(
                'hr_employees.id',
                'hr_employees.first_name',
                'hr_employees.last_name',
                DB::raw('COUNT(workshop_work_orders.id) as total_orders'),
                DB::raw('SUM(CASE WHEN workshop_work_orders.status = "completed" THEN 1 ELSE 0 END) as completed_orders'),
                DB::raw('SUM(CASE WHEN workshop_work_orders.status = "in_progress" THEN 1 ELSE 0 END) as in_progress_orders'),
                DB::raw('SUM(workshop_work_orders.total) as total_revenue')
            )
            ->groupBy('hr_employees.id', 'hr_employees.first_name', 'hr_employees.last_name')
            ->having('total_orders', '>', 0)
            ->orderBy('total_orders', 'desc')
            ->get();
    }

    private function getWorkOrdersReport($tenantId)
    {
        $query = WorkOrder::with(['vehicle', 'mechanic'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('received_at', [$this->dateFrom, $this->dateTo]);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('received_at', 'desc')->get();
    }

    public function exportToPdf()
    {
        // Implementar exportação para PDF
        session()->flash('info', 'Funcionalidade de exportação em desenvolvimento');
    }

    public function exportToExcel()
    {
        // Implementar exportação para Excel
        session()->flash('info', 'Funcionalidade de exportação em desenvolvimento');
    }
}
