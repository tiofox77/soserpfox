<?php

namespace App\Livewire\Events\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentHistory;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Dashboard de Equipamentos')]
class EquipmentDashboard extends Component
{
    public $period = '30days'; // 7days, 30days, 90days, 1year

    public function render()
    {
        $stats = $this->getStatistics();
        $chartData = $this->getChartData();
        $topEquipment = $this->getTopUsedEquipment();
        $recentActivity = $this->getRecentActivity();
        $maintenanceSchedule = $this->getMaintenanceSchedule();
        $overdueEquipment = $this->getOverdueEquipment();

        return view('livewire.events.equipment.equipment-dashboard', compact(
            'stats',
            'chartData',
            'topEquipment',
            'recentActivity',
            'maintenanceSchedule',
            'overdueEquipment'
        ));
    }

    private function getStatistics()
    {
        $totalEquipment = Equipment::where('tenant_id', activeTenantId())->count();
        $totalValue = Equipment::where('tenant_id', activeTenantId())->sum('current_value');
        
        return [
            'total' => $totalEquipment,
            'disponivel' => Equipment::where('tenant_id', activeTenantId())->where('status', 'disponivel')->count(),
            'em_uso' => Equipment::where('tenant_id', activeTenantId())->where('status', 'em_uso')->count(),
            'emprestado' => Equipment::where('tenant_id', activeTenantId())->where('status', 'emprestado')->count(),
            'manutencao' => Equipment::where('tenant_id', activeTenantId())->where('status', 'manutencao')->count(),
            'avariado' => Equipment::where('tenant_id', activeTenantId())->where('status', 'avariado')->count(),
            'total_value' => $totalValue,
            'availability_rate' => $totalEquipment > 0 ? round((Equipment::where('tenant_id', activeTenantId())->where('status', 'disponivel')->count() / $totalEquipment) * 100, 1) : 0,
            'utilization_rate' => $totalEquipment > 0 ? round((Equipment::where('tenant_id', activeTenantId())->whereIn('status', ['em_uso', 'emprestado'])->count() / $totalEquipment) * 100, 1) : 0,
        ];
    }

    private function getChartData()
    {
        $period = $this->getPeriodDates();
        
        // Uso por categoria
        $usageByCategory = Equipment::where('tenant_id', activeTenantId())
            ->select('category', DB::raw('SUM(total_uses) as total'))
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category')
            ->toArray();

        // Status ao longo do tempo
        $statusOverTime = EquipmentHistory::where('tenant_id', activeTenantId())
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->select(DB::raw('DATE(created_at) as date'), 'action_type', DB::raw('COUNT(*) as count'))
            ->groupBy('date', 'action_type')
            ->get()
            ->groupBy('date');

        return [
            'usage_by_category' => $usageByCategory,
            'status_over_time' => $statusOverTime,
        ];
    }

    private function getTopUsedEquipment()
    {
        return Equipment::where('tenant_id', activeTenantId())
            ->orderBy('total_uses', 'desc')
            ->limit(10)
            ->get();
    }

    private function getRecentActivity()
    {
        return EquipmentHistory::where('tenant_id', activeTenantId())
            ->with(['equipment', 'user', 'client', 'event'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    private function getMaintenanceSchedule()
    {
        return Equipment::where('tenant_id', activeTenantId())
            ->whereNotNull('next_maintenance_date')
            ->orderBy('next_maintenance_date', 'asc')
            ->limit(10)
            ->get();
    }

    private function getOverdueEquipment()
    {
        return Equipment::where('tenant_id', activeTenantId())
            ->overdue()
            ->with('borrowedToClient')
            ->get();
    }

    private function getPeriodDates()
    {
        return match($this->period) {
            '7days' => ['start' => now()->subDays(7), 'end' => now()],
            '30days' => ['start' => now()->subDays(30), 'end' => now()],
            '90days' => ['start' => now()->subDays(90), 'end' => now()],
            '1year' => ['start' => now()->subYear(), 'end' => now()],
            default => ['start' => now()->subDays(30), 'end' => now()],
        };
    }
}
