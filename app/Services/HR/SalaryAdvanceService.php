<?php

namespace App\Services\HR;

use App\Models\HR\SalaryAdvance;
use App\Models\HR\Employee;
use App\Models\HR\Contract;
use App\Models\HR\HRSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryAdvanceService
{
    /**
     * Calcular valor máximo permitido para adiantamento
     */
    public function calculateMaxAllowed(Employee $employee): array
    {
        // Buscar percentual configurado nas configurações de RH (padrão: 50%)
        $maxPercentage = HRSetting::get('max_salary_advance_percentage', 50);
        $maxPercentageDecimal = $maxPercentage / 100;
        
        // Tentar pegar salário do contrato ativo, senão usar o salário do employee
        $contract = $employee->contracts()
            ->where('status', 'active')
            ->first();

        $baseSalary = $contract && $contract->base_salary > 0 
            ? $contract->base_salary 
            : ($employee->salary ?? 0);

        if ($baseSalary <= 0) {
            return [
                'base_salary' => 0,
                'max_allowed' => 0,
                'pending_balance' => 0,
                'available_amount' => 0,
                'percentage' => $maxPercentage,
            ];
        }

        $maxAllowed = $baseSalary * $maxPercentageDecimal;

        // Verificar apenas adiantamentos em dedução ativa (que estão bloqueando o limite)
        $pendingAdvances = SalaryAdvance::where('employee_id', $employee->id)
            ->where('status', 'in_deduction')
            ->where('balance', '>', 0)
            ->sum('balance');

        $availableAmount = $maxAllowed - $pendingAdvances;

        return [
            'base_salary' => $baseSalary,
            'max_allowed' => $maxAllowed,
            'pending_balance' => $pendingAdvances,
            'available_amount' => max(0, $availableAmount),
            'percentage' => $maxPercentage,
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

            // Verificar se funcionário tem salário configurado
            if ($limits['base_salary'] <= 0) {
                throw new \Exception('Funcionário não possui salário configurado. Configure um salário ou contrato ativo antes de solicitar adiantamento.');
            }

            // Verificar se há adiantamentos em dedução ativa
            if ($limits['pending_balance'] > 0 && $limits['available_amount'] <= 0) {
                throw new \Exception('Funcionário já possui adiantamentos em dedução totalizando ' . number_format($limits['pending_balance'], 2, ',', '.') . ' Kz. Limite máximo de ' . number_format($limits['max_allowed'], 2, ',', '.') . ' Kz já atingido.');
            }

            // Validar se valor solicitado está dentro do disponível
            if ($data['requested_amount'] > $limits['available_amount']) {
                throw new \Exception('Valor solicitado (' . number_format($data['requested_amount'], 2, ',', '.') . ' Kz) excede o limite disponível de ' . number_format($limits['available_amount'], 2, ',', '.') . ' Kz. Salário base: ' . number_format($limits['base_salary'], 2, ',', '.') . ' Kz (50% = ' . number_format($limits['max_allowed'], 2, ',', '.') . ' Kz)');
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
