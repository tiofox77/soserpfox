<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use App\Models\HR\Vacation;
use App\Models\HR\Department;
use App\Models\HR\Payroll;
use App\Models\HR\Leave;
use App\Models\HR\SalaryAdvance;
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
                // A lingua de quem esta a ver, e nao 'pt_BR' escrita a mao.
                'day' => $date->locale(app()->getLocale())->dayName,
                'present' => Attendance::where('tenant_id', $tenantId)
                    ->whereDate('date', $date)
                    ->where('status', 'present')
                    ->count(),
            ];
        }

        // Resumo Folha de Pagamento
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $latestPayroll = Payroll::where('tenant_id', $tenantId)
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        $payrollSummary = [
            'latest_month' => $latestPayroll ? $latestPayroll->month . '/' . $latestPayroll->year : '—',
            'latest_status' => $latestPayroll->status ?? null,
            'total_net' => $latestPayroll->total_net_salary ?? 0,
            'total_gross' => $latestPayroll->total_gross_salary ?? 0,
            'total_deductions' => $latestPayroll->total_deductions ?? 0,
            'total_employees' => $latestPayroll->processed_employees ?? 0,
            'pending_payrolls' => Payroll::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
        ];

        // Adiantamentos pendentes
        $pendingAdvances = SalaryAdvance::whereHas('employee', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'pending')
            ->count();

        // Licenças pendentes
        $pendingLeaves = Leave::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        // Alertas
        $alerts = [];

        // Alerta de férias pendentes
        if ($pendingVacations > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-umbrella-beach',
                'title' => __('Férias Pendentes'),
                'message' => __(':n pedido(s) de férias à espera de aprovação', ['n' => $pendingVacations]),
                'action' => route('hr.vacations.index'),
                'action_text' => __('Ver Pedidos'),
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
                'title' => __('Documentos a Vencer'),
                'message' => __(':n funcionário(s) com documentos a vencer em breve', ['n' => $expiringDocuments]),
                'action' => route('hr.employees.index'),
                'action_text' => __('Ver Funcionários'),
            ];
        }

        if ($pendingAdvances > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-hand-holding-usd',
                'title' => __('Adiantamentos Pendentes'),
                'message' => __(':n adiantamento(s) à espera de aprovação', ['n' => $pendingAdvances]),
                'action' => route('hr.advances'),
                'action_text' => __('Ver Adiantamentos'),
            ];
        }

        if ($pendingLeaves > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-calendar-times',
                'title' => __('Licenças Pendentes'),
                'message' => __(':n licença(s)/falta(s) à espera de aprovação', ['n' => $pendingLeaves]),
                'action' => route('hr.leaves'),
                'action_text' => __('Ver Licenças'),
            ];
        }

        if ($payrollSummary['pending_payrolls'] > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-money-check-alt',
                'title' => __('Folhas em Rascunho'),
                'message' => __(':n folha(s) de pagamento em rascunho', ['n' => $payrollSummary['pending_payrolls']]),
                'action' => route('hr.payroll'),
                'action_text' => __('Ver Folhas'),
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
            'payrollSummary' => $payrollSummary,
            'alerts' => $alerts,

            'custoMensal'    => $this->custoDaFolhaPorMes($tenantId),
            'porDepartamento' => $this->pessoasPorDepartamento($employeesByDepartment),
            'presencaSemana' => $this->presencaDaSemana($weekAttendance),
            'porContrato'    => $this->pessoasPorContrato($tenantId),
        ])->layout('layouts.app', ['title' => 'Dashboard RH']);
    }

    /**
     * O custo da folha, mês a mês.
     *
     * É a pergunta que a direcção faz e a que este painel não respondia: o
     * resumo mostrava só o último processamento, e um número sozinho não diz
     * se a massa salarial está a crescer.
     */
    private function custoDaFolhaPorMes(int $tenantId): array
    {
        $desde = Carbon::now()->subMonths(11)->startOfMonth();

        $porMes = DB::table('hr_payrolls')
            ->where('tenant_id', $tenantId)
            ->whereRaw('STR_TO_DATE(CONCAT(year, "-", LPAD(month, 2, "0"), "-01"), "%Y-%m-%d") >= ?', [$desde->format('Y-m-d')])
            ->groupBy('year', 'month')
            ->selectRaw('year, month, SUM(total_net_salary) as total')
            ->get()
            ->keyBy(fn ($l) => sprintf('%04d-%02d', $l->year, $l->month));

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $data = $desde->copy()->addMonths($m);

            $etiquetas[] = $data->translatedFormat('M/y');
            $valores[] = (float) ($porMes[$data->format('Y-m')]->total ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** Pessoas por departamento — já vinha calculado, faltava desenhá-lo. */
    private function pessoasPorDepartamento($porDepartamento): array
    {
        return [
            'etiquetas' => $porDepartamento->map(fn ($d) => $d->department->name ?? __('Sem departamento'))->all(),
            'valores'   => $porDepartamento->map(fn ($d) => (int) $d->total)->all(),
        ];
    }

    /** Presença ao longo da semana — idem. */
    private function presencaDaSemana(array $semana): array
    {
        return [
            'etiquetas' => array_column($semana, 'date'),
            'valores'   => array_map('intval', array_column($semana, 'present')),
        ];
    }

    /**
     * Pessoas por situação de emprego.
     *
     * Diz numa vista de olhos como está composta a equipa — uma proporção que
     * muda a leitura de todos os outros números deste painel.
     */
    private function pessoasPorContrato(int $tenantId): array
    {
        $linhas = DB::table('hr_employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->groupBy('employment_status')
            ->selectRaw('COALESCE(employment_status, "—") as tipo, COUNT(*) as total')
            ->orderByDesc('total')
            ->get();

        $rotulos = [
            'permanent'  => __('Efectivo'),
            'fixed_term' => __('A termo'),
            'temporary'  => __('Temporário'),
            'internship' => __('Estágio'),
            'contract'   => __('Prestação de serviços'),
            'probation'  => __('Período experimental'),
        ];

        return [
            'etiquetas' => $linhas->map(fn ($l) => $rotulos[$l->tipo] ?? $l->tipo)->all(),
            'valores'   => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }
}
