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

    /**
     * O intervalo escolhido, do INICIO do primeiro dia ao FIM do ultimo.
     *
     * As colunas sao DATETIME. Com datas secas o limite de cima era a
     * meia-noite do ultimo dia e o trabalho de hoje nao contava.
     */
    private function intervalo(): array
    {
        return [
            \Carbon\Carbon::parse($this->dateFrom)->startOfDay(),
            \Carbon\Carbon::parse($this->dateTo)->endOfDay(),
        ];
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();

        $intervalo = $this->intervalo();
        
        // KPIs Principais
        $totalOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('received_at', $intervalo)
            ->count();
            
        $pendingOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->count();
            
        $inProgressOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', 'in_progress')
            ->count();
            
        $completedOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', $intervalo)
            ->count();
            
        $totalRevenue = WorkOrder::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('received_at', $intervalo)
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
            ->whereBetween('workshop_work_orders.received_at', $intervalo)
            ->select('workshop_services.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(workshop_work_order_items.subtotal) as revenue'))
            ->groupBy('workshop_services.id', 'workshop_services.name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
            
        // OS por status
        $ordersByStatus = WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('received_at', $intervalo)
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

            'receitaMensal'  => $this->receitaPorMes($tenantId),
            'porEstado'      => $this->ordensPorEstado($ordersByStatus),
            'servicosTop'    => $this->receitaPorServico($topServices),
            'porMecanico'    => $this->ordensPorMecanico($tenantId),
        ]);
    }

    /**
     * A facturação da oficina mês a mês.
     *
     * O painel mostrava só o total do período escolhido. Um total não diz se a
     * oficina está a crescer — e é essa a pergunta que se faz a seguir.
     */
    private function receitaPorMes(int $tenantId): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('received_at', '>=', $desde)
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(received_at, '%Y-%m') as mes, SUM(total) as total")
            ->pluck('total', 'mes');

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $data = $desde->copy()->addMonths($m);

            $etiquetas[] = $data->translatedFormat('M/y');
            $valores[] = (float) ($porMes[$data->format('Y-m')] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** As ordens por estado — já vinha calculado, faltava desenhá-lo. */
    private function ordensPorEstado($porEstado): array
    {
        $rotulos = [
            'pending'     => __('Pendente'),
            'scheduled'   => __('Agendada'),
            'in_progress' => __('Em curso'),
            'completed'   => __('Concluída'),
            'delivered'   => __('Entregue'),
            'cancelled'   => __('Cancelada'),
        ];

        return [
            'etiquetas' => $porEstado->map(fn ($o) => $rotulos[$o->status] ?? $o->status)->all(),
            'chaves'    => $porEstado->pluck('status')->all(),
            'valores'   => $porEstado->map(fn ($o) => (int) $o->count)->all(),
        ];
    }

    /** Quanto rende cada serviço — e não só quantas vezes é feito. */
    private function receitaPorServico($topServices): array
    {
        return [
            'etiquetas' => $topServices->pluck('name')->all(),
            'valores'   => $topServices->map(fn ($s) => (float) $s->revenue)->all(),
        ];
    }

    /**
     * A carga por mecânico.
     *
     * Diz quem está sobrecarregado, que é o que decide a próxima marcação — e
     * não havia forma de o ver sem abrir a lista de ordens uma a uma.
     */
    private function ordensPorMecanico(int $tenantId): array
    {
        $linhas = DB::table('workshop_work_orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.mechanic_id')
            ->where('o.tenant_id', $tenantId)
            ->whereIn('o.status', ['pending', 'scheduled', 'in_progress'])
            ->groupBy('u.id', 'u.name')
            ->selectRaw('COALESCE(u.name, "' . __('Por atribuir') . '") as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }
}
