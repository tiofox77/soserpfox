<?php

namespace App\Services\HR;

use App\Models\HR\Leave;
use App\Models\HR\Employee;
use App\Models\HR\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    /**
     * Calcular dias úteis entre duas datas
     */
    public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            if ($currentDate->isWeekday()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }

        return $workingDays;
    }

    /**
     * Criar solicitação de licença
     */
    public function createLeaveRequest(array $data): Leave
    {
        DB::beginTransaction();

        try {
            $employee = Employee::findOrFail($data['employee_id']);

            // Calcular dias
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            $totalDays = $endDate->diffInDays($startDate) + 1;
            $workingDays = $this->calculateWorkingDays($startDate, $endDate);

            // Verificar sobreposição com férias
            $hasVacationOverlap = $this->checkVacationOverlap($data['employee_id'], $startDate, $endDate);
            if ($hasVacationOverlap) {
                throw new \Exception('O funcionário já possui férias programadas neste período!');
            }

            // Verificar sobreposição com outras licenças
            $hasLeaveOverlap = $this->checkLeaveOverlap($data['employee_id'], $startDate, $endDate);
            if ($hasLeaveOverlap) {
                throw new \Exception('O funcionário já possui licença registrada neste período!');
            }

            // Calcular dedução se não for pago
            $deductionAmount = 0;
            if (isset($data['paid']) && !$data['paid']) {
                $contract = $employee->contracts()->where('status', 'active')->first();
                if ($contract) {
                    $dailyRate = $contract->base_salary / 22;
                    $deductionAmount = $dailyRate * $workingDays;
                }
            }

            // Gerar número de licença
            $leaveNumber = 'LIC-' . date('Y') . '-' . str_pad(Leave::count() + 1, 5, '0', STR_PAD_LEFT);

            // Criar registro
            $leave = Leave::create([
                'tenant_id' => $data['tenant_id'],
                'employee_id' => $data['employee_id'],
                'leave_number' => $leaveNumber,
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $totalDays,
                'working_days' => $workingDays,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'has_medical_certificate' => $data['has_medical_certificate'] ?? false,
                'document_path' => $data['document_path'] ?? null,
                'status' => 'pending',
                'paid' => $data['paid'] ?? true,
                'deduction_amount' => $deductionAmount,
            ]);

            DB::commit();

            return $leave;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Verificar sobreposição com férias
     */
    public function checkVacationOverlap(int $employeeId, $startDate, $endDate): bool
    {
        return Vacation::where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'in_progress'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->exists();
    }

    /**
     * Verificar sobreposição com outras licenças
     */
    public function checkLeaveOverlap(int $employeeId, $startDate, $endDate, ?int $excludeLeaveId = null): bool
    {
        $query = Leave::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        return $query->exists();
    }

    /**
     * Obter histórico de licenças do funcionário
     */
    public function getEmployeeLeaveHistory(int $employeeId, ?int $year = null)
    {
        $query = Leave::where('employee_id', $employeeId)
            ->with(['approvedBy', 'rejectedBy']);

        if ($year) {
            $query->whereYear('start_date', $year);
        }

        return $query->latest()->get();
    }

    /**
     * Obter estatísticas de licenças do funcionário
     */
    public function getEmployeeLeaveStats(int $employeeId, int $year): array
    {
        $leaves = Leave::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->get();

        $stats = [
            'total_days' => $leaves->sum('working_days'),
            'by_type' => [],
        ];

        foreach ($leaves->groupBy('leave_type') as $type => $typeLeaves) {
            $stats['by_type'][$type] = [
                'count' => $typeLeaves->count(),
                'days' => $typeLeaves->sum('working_days'),
            ];
        }

        return $stats;
    }
}
