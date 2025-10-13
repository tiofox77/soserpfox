<?php

namespace App\Services\HR;

use App\Models\HR\SalaryAdvance;
use App\Models\HR\Employee;
use App\Models\HR\Contract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryAdvanceService
{
    /**
     * Percentual máximo permitido para adiantamento (50% do salário)
     */
    const MAX_ADVANCE_PERCENTAGE = 0.5;

    /**
     * Calcular valor máximo permitido para adiantamento
     */
    public function calculateMaxAllowed(Employee $employee): array
    {
        $contract = $employee->contracts()
            ->where('status', 'active')
            ->first();

        if (!$contract) {
            return [
                'base_salary' => 0,
                'max_allowed' => 0,
                'percentage' => self::MAX_ADVANCE_PERCENTAGE * 100,
            ];
        }

        $baseSalary = $contract->base_salary;
        $maxAllowed = $baseSalary * self::MAX_ADVANCE_PERCENTAGE;

        // Verificar adiantamentos pendentes
        $pendingAdvances = SalaryAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'paid', 'in_deduction'])
            ->sum('balance');

        $availableAmount = $maxAllowed - $pendingAdvances;

        return [
            'base_salary' => $baseSalary,
            'max_allowed' => $maxAllowed,
            'pending_balance' => $pendingAdvances,
            'available_amount' => max(0, $availableAmount),
            'percentage' => self::MAX_ADVANCE_PERCENTAGE * 100,
        ];
    }

    /**
     * Criar solicitação de adiantamento
     */
    public function createAdvanceRequest(array $data): SalaryAdvance
    {
        DB::beginTransaction();

        try {
            $employee = Employee::findOrFail($data['employee_id']);

            // Calcular limites
            $limits = $this->calculateMaxAllowed($employee);

            if ($limits['available_amount'] <= 0) {
                throw new \Exception('Funcionário já possui adiantamentos pendentes. Não é possível solicitar novo adiantamento.');
            }

            if ($data['requested_amount'] > $limits['available_amount']) {
                throw new \Exception('Valor solicitado excede o limite disponível de ' . number_format($limits['available_amount'], 2, ',', '.') . ' Kz');
            }

            // Calcular valor da parcela
            $installments = $data['installments'] ?? 1;
            $installmentAmount = round($data['requested_amount'] / $installments, 2);

            // Gerar número de adiantamento
            $advanceNumber = 'ADV-' . date('Y') . '-' . str_pad(SalaryAdvance::count() + 1, 5, '0', STR_PAD_LEFT);

            // Criar registro
            $advance = SalaryAdvance::create([
                'tenant_id' => $data['tenant_id'],
                'employee_id' => $data['employee_id'],
                'advance_number' => $advanceNumber,
                'requested_amount' => $data['requested_amount'],
                'base_salary' => $limits['base_salary'],
                'max_allowed' => $limits['max_allowed'],
                'installments' => $installments,
                'installment_amount' => $installmentAmount,
                'request_date' => $data['request_date'] ?? now(),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);

            DB::commit();

            return $advance;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obter histórico de adiantamentos do funcionário
     */
    public function getEmployeeAdvanceHistory(int $employeeId, ?int $year = null)
    {
        $query = SalaryAdvance::where('employee_id', $employeeId)
            ->with(['approvedBy', 'rejectedBy', 'paidBy']);

        if ($year) {
            $query->whereYear('request_date', $year);
        }

        return $query->latest()->get();
    }

    /**
     * Obter total de adiantamentos do funcionário
     */
    public function getEmployeeTotalAdvances(int $employeeId, int $year): array
    {
        $advances = SalaryAdvance::where('employee_id', $employeeId)
            ->whereIn('status', ['paid', 'in_deduction', 'completed'])
            ->whereYear('request_date', $year)
            ->get();

        return [
            'total_requested' => $advances->sum('requested_amount'),
            'total_approved' => $advances->sum('approved_amount'),
            'total_balance' => $advances->sum('balance'),
            'count' => $advances->count(),
        ];
    }

    /**
     * Processar dedução mensal de adiantamentos
     */
    public function processMonthlyDeductions(int $employeeId): array
    {
        $advances = SalaryAdvance::where('employee_id', $employeeId)
            ->where('status', 'in_deduction')
            ->where('balance', '>', 0)
            ->get();

        $totalDeduction = 0;
        $processed = [];

        foreach ($advances as $advance) {
            $deductionAmount = min($advance->installment_amount, $advance->balance);
            $advance->recordInstallmentPayment($deductionAmount);
            
            $totalDeduction += $deductionAmount;
            $processed[] = [
                'advance_id' => $advance->id,
                'advance_number' => $advance->advance_number,
                'deduction_amount' => $deductionAmount,
                'remaining_balance' => $advance->fresh()->balance,
            ];
        }

        return [
            'total_deduction' => $totalDeduction,
            'advances_processed' => $processed,
        ];
    }
}
