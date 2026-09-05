<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\Attendance;
use App\Models\HR\Leave;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\Department;
use App\Services\HR\VacationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HRReports extends Component
{
    public $reportType = 'salary_map';
    public $year;
    public $month;
    public $departmentId = '';

    public function mount()
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();

        $reportData = match($this->reportType) {
            'salary_map' => $this->getSalaryMapData($tenantId),
            'department_costs' => $this->getDepartmentCostsData($tenantId),
            'attendance_summary' => $this->getAttendanceSummaryData($tenantId),
            'vacation_balance' => $this->getVacationBalanceData($tenantId),
            'headcount' => $this->getHeadcountData($tenantId),
            default => [],
        };

        return view('livewire.hr.reports.reports', [
            'departments' => $departments,
            'reportData' => $reportData,
        ])->layout('layouts.app', ['title' => 'Relatórios RH']);
    }

    private function getSalaryMapData($tenantId)
    {
        $payroll = Payroll::where('tenant_id', $tenantId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$payroll) return ['items' => collect(), 'totals' => null];

        $query = PayrollItem::where('payroll_id', $payroll->id)
            ->with(['employee.department', 'employee.position']);

        if ($this->departmentId) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $this->departmentId));
        }

        $items = $query->get()->sortBy('employee.full_name');

        return [
            'items' => $items,
            'totals' => [
                'base_salary' => $items->sum('base_salary'),
                'food_allowance' => $items->sum('food_allowance'),
                'transport_allowance' => $items->sum('transport_allowance'),
                'gross_salary' => $items->sum('gross_salary'),
                'inss_employee' => $items->sum('inss_employee'),
                'irt_amount' => $items->sum('irt_amount'),
                'total_deductions' => $items->sum('total_deductions'),
                'net_salary' => $items->sum('net_salary'),
            ],
        ];
    }

    private function getDepartmentCostsData($tenantId)
    {
        $payroll = Payroll::where('tenant_id', $tenantId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$payroll) return collect();

        return PayrollItem::where('payroll_id', $payroll->id)
            ->with('employee.department')
            ->get()
            ->groupBy(fn($item) => $item->employee->department->name ?? 'Sem Departamento')
            ->map(function ($items, $dept) {
                return [
                    'department' => $dept,
                    'employees' => $items->count(),
                    'total_gross' => $items->sum('gross_salary'),
                    'total_net' => $items->sum('net_salary'),
                    'total_inss' => $items->sum('inss_employee') + $items->sum('inss_employer'),
                    'total_irt' => $items->sum('irt_amount'),
                    'avg_salary' => $items->count() > 0 ? $items->sum('net_salary') / $items->count() : 0,
                ];
            })
            ->sortByDesc('total_gross');
    }

    private function getAttendanceSummaryData($tenantId)
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = Employee::where('tenant_id', $tenantId)->where('status', 'active');
        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        }

        return $query->get()->map(function ($employee) use ($start, $end) {
            $attendances = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$start, $end])
                ->get();

            $present = $attendances->where('status', 'present')->count();
            $late = $attendances->where('status', 'late')->count();
            $halfDay = $attendances->where('status', 'half_day')->count();
            $absent = $attendances->where('status', 'absent')->count();
            $justifiedStatuses = [
                'sick', 'vacation', 'sick_leave', 'on_leave',
                'maternity_leave', 'paternity_leave',
            ];
            $justified = $attendances->whereIn('status', $justifiedStatuses)->count();
            $workedEquivalent = $present + $late + ($halfDay * 0.5);
            $total = $attendances->count();
            $attendanceRate = $total > 0
                ? round(min(100, (($workedEquivalent + $justified) / $total) * 100), 1)
                : 0;

            return [
                'employee' => $employee,
                'present' => $present,
                'late' => $late,
                'half_day' => $halfDay,
                'worked_equivalent' => $workedEquivalent,
                'absent' => $absent,
                'justified' => $justified,
                'total' => $total,
                'attendance_rate' => $attendanceRate,
            ];
        })->sortBy('employee.full_name');
    }

    private function getVacationBalanceData($tenantId)
    {
        $query = Employee::where('tenant_id', $tenantId)->where('status', 'active');
        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        }

        $vacations = app(VacationService::class);

        return $query->get()->map(function ($employee) use ($vacations) {
            $balance = $vacations->getAvailableVacationDays($employee, (int) $this->year);

            return [
                'employee' => $employee,
                'entitled' => $balance['entitled'],
                'taken' => $balance['used'],
                'remaining' => $balance['available'],
            ];
        })->sortBy('employee.full_name');
    }

    private function getHeadcountData($tenantId)
    {
        $data = [];
        for ($m = 1; $m <= 12; $m++) {
            $month = Carbon::create($this->year, $m, 1)->startOfMonth();
            if ($month->gt(now()->startOfMonth())) break;

            // O mês corrente é uma fotografia de hoje; os anteriores usam o
            // último dia do mês. Assim Setembro não desaparece até ao dia 30.
            $date = $month->isSameMonth(now()) ? now()->endOfDay() : $month->copy()->endOfMonth();

            $count = Employee::where('tenant_id', $tenantId)
                ->where('hire_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('termination_date')->orWhere('termination_date', '>', $date);
                })
                ->count();

            $data[] = [
                'month' => $m,
                'month_name' => Carbon::create($this->year, $m, 1)->locale('pt_BR')->monthName,
                'count' => $count,
            ];
        }

        return $data;
    }
}
