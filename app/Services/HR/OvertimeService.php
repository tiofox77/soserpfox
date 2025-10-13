<?php

namespace App\Services\HR;

use App\Models\HR\Overtime;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    /**
     * Multiplicadores segundo legislação angolana
     */
    const MULTIPLIERS = [
        'weekday' => 1.5,  // 50% adicional
        'weekend' => 2.0,  // 100% adicional
        'holiday' => 2.0,  // 100% adicional
        'night' => 1.25,   // 25% adicional
    ];

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
        if ($date->isWeekend()) {
            return 'weekend';
        }

        // Aqui você pode adicionar lógica para verificar feriados
        // if ($this->isHoliday($date)) {
        //     return 'holiday';
        // }

        return 'weekday';
    }

    /**
     * Calcular valores de hora extra
     */
    public function calculateOvertimePay(Employee $employee, float $hours, string $overtimeType): array
    {
        $contract = $employee->contracts()
            ->where('status', 'active')
            ->first();

        if (!$contract) {
            return [
                'hourly_rate' => 0,
                'multiplier' => 0,
                'overtime_rate' => 0,
                'total_amount' => 0,
            ];
        }

        // Taxa hora normal (salário mensal / 176 horas mensais)
        $monthlyHours = 176; // 22 dias x 8 horas
        $hourlyRate = $contract->base_salary / $monthlyHours;

        // Multiplicador
        $multiplier = self::MULTIPLIERS[$overtimeType] ?? 1.5;

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

            // Calcular horas
            $totalHours = $this->calculateHours($data['start_time'], $data['end_time']);

            if ($totalHours <= 0) {
                throw new \Exception('O horário de término deve ser posterior ao horário de início.');
            }

            // Determinar tipo de hora extra
            $date = Carbon::parse($data['date']);
            $overtimeType = $data['overtime_type'] ?? $this->determineOvertimeType($date);

            // Calcular valores
            $calculations = $this->calculateOvertimePay($employee, $totalHours, $overtimeType);

            // Gerar número de hora extra
            $overtimeNumber = 'HE-' . date('Y') . '-' . str_pad(Overtime::count() + 1, 5, '0', STR_PAD_LEFT);

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
                'multiplier' => $calculations['multiplier'],
                'hourly_rate' => $calculations['hourly_rate'],
                'overtime_rate' => $calculations['overtime_rate'],
                'total_amount' => $calculations['total_amount'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
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
