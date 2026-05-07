<?php

namespace App\Services\HR;

use App\Models\HR\Overtime;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use App\Models\HR\HRSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    /**
     * Obter multiplicadores da legislação angolana via HRSetting (fallback hardcoded)
     */
    public function getMultipliers(): array
    {
        $weekdayRate = 1 + ((float) HRSetting::get('overtime_weekday_rate', 50) / 100);
        return [
            'regular' => $weekdayRate,
            'weekday' => $weekdayRate,
            'weekend' => 1 + ((float) HRSetting::get('overtime_weekend_rate', 100) / 100),
            'holiday' => 1 + ((float) HRSetting::get('overtime_holiday_rate', 100) / 100),
            'night'   => 1 + ((float) HRSetting::get('overtime_night_rate', 25) / 100),
        ];
    }

    /**
     * Limite legal mensal de horas extras (Angola: 40h/mês por defeito)
     */
    public function getMonthlyLimit(): float
    {
        return (float) HRSetting::get('overtime_monthly_limit', 40);
    }

    /**
     * Calcular horas entre dois horários
     */
    public function calculateHours(string $startTime, string $endTime): float
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return round($start->diffInHours($end, true), 2);
    }

    /**
     * Determinar tipo de hora extra baseado na data
     */
    public function determineOvertimeType(Carbon $date): string
    {
        $workOnSaturday = (bool) HRSetting::get('work_on_saturday', false);

        if ($date->isSunday()) {
            return 'weekend';
        }

        if ($date->isSaturday() && !$workOnSaturday) {
            return 'weekend';
        }

        // Verificar feriados angolanos
        if (\App\Helpers\PayrollCalculatorHelper::isAngolanHoliday($date)) {
            return 'holiday';
        }

        return 'weekday';
    }

    /**
     * Calcular valores de hora extra
     */
    public function calculateOvertimePay(Employee $employee, float $hours, string $overtimeType): array
    {
        // Tentar buscar contrato ativo
        $contract = $employee->contracts()
            ->where('status', 'active')
            ->first();

        // Se não tiver contrato, usar salário do funcionário
        $baseSalary = $contract ? $contract->base_salary : ($employee->base_salary ?? $employee->salary ?? 0);

        $multipliers = $this->getMultipliers();

        if ($baseSalary == 0) {
            return [
                'hourly_rate' => 0,
                'multiplier' => $multipliers[$overtimeType] ?? 1.5,
                'overtime_rate' => 0,
                'total_amount' => 0,
            ];
        }

        // Taxa hora normal (salário mensal / horas mensais via HRSetting)
        $workingDays = (float) HRSetting::get('monthly_working_days', 22);
        $hoursPerDay = (float) HRSetting::get('working_hours_per_day', 8);
        $monthlyHours = $workingDays * $hoursPerDay;
        $hourlyRate = $baseSalary / $monthlyHours;

        // Multiplicador
        $multiplier = $multipliers[$overtimeType] ?? 1.5;

        // Taxa hora extra
        $overtimeRate = $hourlyRate * $multiplier;

        // Total a receber
        $totalAmount = $overtimeRate * $hours;

        return [
            'hourly_rate' => round($hourlyRate, 2),
            'multiplier' => $multiplier,
            'overtime_rate' => round($overtimeRate, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * Criar registro de hora extra
     */
    public function createOvertimeRecord(array $data): Overtime
    {
        DB::beginTransaction();

        try {
            $employee = Employee::findOrFail($data['employee_id']);

            // Calcular horas conforme input_type
            $inputType = $data['input_type'] ?? 'time_range';

            if ($inputType === 'time_range') {
                if (empty($data['start_time']) || empty($data['end_time'])) {
                    throw new \Exception('Hora início e hora fim são obrigatórias para intervalo de horas.');
                }
                $totalHours = $this->calculateHours($data['start_time'], $data['end_time']);
                if ($totalHours <= 0) {
                    throw new \Exception('O horário de término deve ser posterior ao horário de início.');
                }
            } else {
                // daily_hours ou monthly_hours — horas diretas
                $totalHours = (float) ($data['direct_hours'] ?? 0);
                if ($totalHours <= 0) {
                    throw new \Exception('O número de horas deve ser superior a zero.');
                }
            }

            // Determinar tipo de hora extra
            $date = Carbon::parse($data['date']);
            $overtimeType = $data['overtime_type'] ?? $this->determineOvertimeType($date);

            // Calcular valores
            $calculations = $this->calculateOvertimePay($employee, $totalHours, $overtimeType);

            // Gerar número de hora extra
            $overtimeNumber = 'HE-' . date('Y') . '-' . str_pad(Overtime::count() + 1, 5, '0', STR_PAD_LEFT);

            // Verificar limite legal mensal
            $monthStart = Carbon::parse($data['date'])->startOfMonth();
            $monthEnd = Carbon::parse($data['date'])->endOfMonth();
            $existingHours = Overtime::where('employee_id', $data['employee_id'])
                ->where('tenant_id', $data['tenant_id'])
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->sum('total_hours');
            $monthlyLimit = $this->getMonthlyLimit();
            if (($existingHours + $totalHours) > $monthlyLimit) {
                throw new \Exception("Limite legal mensal de {$monthlyLimit}h extras excedido. Já registadas: {$existingHours}h.");
            }

            // Criar registro
            $overtime = Overtime::create([
                'tenant_id' => $data['tenant_id'],
                'employee_id' => $data['employee_id'],
                'attendance_id' => $data['attendance_id'] ?? null,
                'overtime_number' => $overtimeNumber,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'total_hours' => $totalHours,
                'overtime_type' => $overtimeType,
                'input_type' => $data['input_type'] ?? 'time_range',
                'direct_hours' => $data['direct_hours'] ?? null,
                'period_type' => $data['period_type'] ?? null,
                'is_night_shift' => ($overtimeType === 'night') || ($data['is_night_shift'] ?? false),
                'multiplier' => $calculations['multiplier'],
                'hourly_rate' => $calculations['hourly_rate'],
                'overtime_rate' => $calculations['overtime_rate'],
                'rate' => $calculations['overtime_rate'],
                'amount' => $calculations['total_amount'],
                'total_amount' => $calculations['total_amount'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return $overtime;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Criar hora extra automática a partir de presença
     */
    public function createFromAttendance(Attendance $attendance): ?Overtime
    {
        if (!$attendance->overtime_hours || $attendance->overtime_hours <= 0) {
            return null;
        }

        // Verificar se já existe hora extra registrada para esta presença
        $existing = Overtime::where('attendance_id', $attendance->id)->first();
        if ($existing) {
            return $existing;
        }

        $employee = $attendance->employee;
        $date = $attendance->date;

        // Calcular horário de hora extra (após expediente normal)
        $startTime = $attendance->check_out ?? '18:00:00';
        $endTime = Carbon::parse($startTime)->addHours($attendance->overtime_hours)->format('H:i:s');

        return $this->createOvertimeRecord([
            'tenant_id' => $attendance->tenant_id,
            'employee_id' => $attendance->employee_id,
            'attendance_id' => $attendance->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => 'Gerado automaticamente da presença',
        ]);
    }

    /**
     * Obter histórico de horas extras do funcionário
     */
    public function getEmployeeOvertimeHistory(int $employeeId, ?int $year = null, ?int $month = null)
    {
        $query = Overtime::where('employee_id', $employeeId)
            ->with(['approvedBy', 'attendance']);

        if ($year) {
            $query->whereYear('date', $year);
        }

        if ($month) {
            $query->whereMonth('date', $month);
        }

        return $query->latest('date')->get();
    }

    /**
     * Obter total de horas extras do funcionário
     */
    public function getEmployeeOvertimeStats(int $employeeId, int $year, ?int $month = null): array
    {
        $query = Overtime::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereYear('date', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        $overtimes = $query->get();

        $stats = [
            'total_hours' => $overtimes->sum('total_hours'),
            'total_amount' => $overtimes->sum('total_amount'),
            'count' => $overtimes->count(),
            'by_type' => [],
        ];

        foreach ($overtimes->groupBy('overtime_type') as $type => $typeOvertimes) {
            $stats['by_type'][$type] = [
                'count' => $typeOvertimes->count(),
                'hours' => $typeOvertimes->sum('total_hours'),
                'amount' => $typeOvertimes->sum('total_amount'),
            ];
        }

        return $stats;
    }
}
