<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use App\Models\HR\Vacation;
use App\Models\HR\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HRDashboard extends Component
{
    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Estatísticas Gerais
        $stats = [
            'total_employees' => Employee::where('tenant_id', $tenantId)->count(),
            'active_employees' => Employee::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'inactive_employees' => Employee::where('tenant_id', $tenantId)->where('status', 'inactive')->count(),
            'on_vacation' => Vacation::where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count(),
        ];

        // Presenças do Dia
        $attendanceToday = [
            'present' => Attendance::where('tenant_id', $tenantId)
                ->whereDate('date', $today)
                ->where('status', 'present')
                ->count(),
            'late' => Attendance::where('tenant_id', $tenantId)
                ->whereDate('date', $today)
                ->where('status', 'late')
                ->count(),
            'absent' => Attendance::where('tenant_id', $tenantId)
                ->whereDate('date', $today)
                ->where('status', 'absent')
                ->count(),
            'total' => Attendance::where('tenant_id', $tenantId)
                ->whereDate('date', $today)
                ->count(),
        ];

        // Férias Pendentes
        $pendingVacations = Vacation::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        // Aniversariantes do Mês
        $birthdays = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereMonth('birth_date', Carbon::now()->month)
            ->orderByRaw('DAY(birth_date)')
            ->take(5)
            ->get();

        // Funcionários por Departamento
        $employeesByDepartment = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('department_id', DB::raw('count(*) as total'))
            ->with('department')
            ->groupBy('department_id')
            ->get();

        // Últimas Admissões
        $recentHires = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('hire_date')
            ->orderBy('hire_date', 'desc')
            ->take(5)
            ->get();

        // Próximas Férias
        $upcomingVacations = Vacation::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('start_date', '>', $today)
            ->orderBy('start_date')
            ->with('employee')
            ->take(5)
            ->get();

        // Presenças da Semana (últimos 7 dias)
        $weekAttendance = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $weekAttendance[] = [
                'date' => $date->format('d/m'),
                'day' => $date->locale('pt_BR')->dayName,
                'present' => Attendance::where('tenant_id', $tenantId)
                    ->whereDate('date', $date)
                    ->where('status', 'present')
                    ->count(),
            ];
        }

        // Alertas
        $alerts = [];

        // Alerta de férias pendentes
        if ($pendingVacations > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-umbrella-beach',
                'title' => 'Férias Pendentes',
                'message' => "$pendingVacations solicitação(ões) de férias aguardando aprovação",
                'action' => route('hr.vacations.index'),
                'action_text' => 'Ver Solicitações',
            ];
        }

        // Alerta de documentos vencendo
        $expiringDocuments = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('bi_expiry_date', '<=', Carbon::now()->addMonth())
            ->where('bi_expiry_date', '>=', $today)
            ->count();

        if ($expiringDocuments > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'fa-id-card',
                'title' => 'Documentos Vencendo',
                'message' => "$expiringDocuments funcionário(s) com documentos vencendo em breve",
                'action' => route('hr.employees.index'),
                'action_text' => 'Ver Funcionários',
            ];
        }

        return view('livewire.hr.dashboard', [
            'stats' => $stats,
            'attendanceToday' => $attendanceToday,
            'pendingVacations' => $pendingVacations,
            'birthdays' => $birthdays,
            'employeesByDepartment' => $employeesByDepartment,
            'recentHires' => $recentHires,
            'upcomingVacations' => $upcomingVacations,
            'weekAttendance' => $weekAttendance,
            'alerts' => $alerts,
        ])->layout('layouts.app', ['title' => 'Dashboard RH']);
    }
}
