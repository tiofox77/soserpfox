<?php

namespace App\Livewire\Salon;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Salon\Appointment;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Relatório de Tempos - Salão')]
class TimeReport extends Component
{
    public $dateFrom;
    public $dateTo;
    public $professionalId = '';
    public $serviceId = '';

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function render()
    {
        // Buscar atendimentos concluídos com tempo registrado
        $query = Appointment::forTenant()
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->with(['client', 'professional', 'services.service']);

        if ($this->professionalId) {
            $query->where('professional_id', $this->professionalId);
        }

        $appointments = $query->orderBy('date', 'desc')->orderBy('start_time', 'desc')->get();

        // Estatísticas gerais
        $stats = $this->calculateStats($appointments);

        // Estatísticas por profissional
        $byProfessional = $this->statsByProfessional();

        // Estatísticas por serviço
        $byService = $this->statsByService();

        // Profissionais para filtro
        $professionals = Professional::forTenant()->active()->orderBy('name')->get();
        
        // Serviços para filtro
        $services = Service::forTenant()->where('is_active', true)->orderBy('name')->get();

        return view('livewire.salon.reports.time-report', [
            'appointments' => $appointments,
            'stats' => $stats,
            'byProfessional' => $byProfessional,
            'byService' => $byService,
            'professionals' => $professionals,
            'services' => $services,
        ]);
    }

    private function calculateStats($appointments)
    {
        $completed = $appointments->filter(fn($a) => $a->actual_duration !== null);
        
        if ($completed->isEmpty()) {
            return [
                'total_appointments' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'avg_wait' => 0,
                'on_time' => 0,
                'delayed' => 0,
                'faster' => 0,
                'efficiency' => 0,
            ];
        }

        $totalTime = $completed->sum('actual_duration');
        $avgTime = $completed->avg('actual_duration');
        $avgWait = $completed->filter(fn($a) => $a->wait_time !== null)->avg('wait_time') ?? 0;
        
        // Contagem de atendimentos no tempo, atrasados e mais rápidos
        $onTime = $completed->filter(fn($a) => abs($a->time_difference ?? 0) <= 5)->count(); // ±5min tolerância
        $delayed = $completed->filter(fn($a) => ($a->time_difference ?? 0) > 5)->count();
        $faster = $completed->filter(fn($a) => ($a->time_difference ?? 0) < -5)->count();
        
        // Eficiência: tempo previsto / tempo real (limitada a 200%)
        $totalPrevisto = $completed->sum('total_duration');
        $efficiency = ($totalPrevisto > 0 && $totalTime > 0) 
            ? min(200, round(($totalPrevisto / $totalTime) * 100, 1)) 
            : 100;

        return [
            'total_appointments' => $completed->count(),
            'total_time' => $totalTime,
            'avg_time' => round($avgTime),
            'avg_wait' => round($avgWait),
            'on_time' => $onTime,
            'delayed' => $delayed,
            'faster' => $faster,
            'efficiency' => $efficiency,
        ];
    }

    private function statsByProfessional()
    {
        return Appointment::forTenant()
            ->select(
                'professional_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_duration'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as total_time'),
                DB::raw('AVG(total_duration) as avg_estimated'),
                DB::raw('SUM(total) as revenue')
            )
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->groupBy('professional_id')
            ->with('professional')
            ->get()
            ->map(function ($item) {
                $item->efficiency = ($item->avg_estimated > 0 && $item->avg_duration > 0)
                    ? min(200, round(($item->avg_estimated / $item->avg_duration) * 100, 1)) 
                    : 100;
                $item->avg_duration = round($item->avg_duration);
                return $item;
            });
    }

    private function statsByService()
    {
        return DB::table('salon_appointment_services as sas')
            ->join('salon_appointments as sa', 'sas.appointment_id', '=', 'sa.id')
            ->join('invoicing_products as ss', 'sas.service_id', '=', 'ss.id')
            ->select(
                'sas.service_id',
                'ss.name as service_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(sas.total) as revenue')
            )
            ->where('sa.tenant_id', activeTenantId())
            ->where('sa.status', 'completed')
            ->whereBetween('sa.date', [$this->dateFrom, $this->dateTo])
            ->groupBy('sas.service_id', 'ss.name')
            ->orderByDesc('total')
            ->get();
    }

    public function formatMinutes($minutes)
    {
        if (!$minutes) return '-';
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours > 0 ? "{$hours}h {$mins}min" : "{$mins}min";
    }
}
