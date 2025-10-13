<?php

namespace App\Services\HR;

use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Criar nova folha de pagamento para um mês
     */
    public function createPayroll(int $tenantId, int $year, int $month): Payroll
    {
        $periodStart = Carbon::create($year, $month, 1);
        $periodEnd = $periodStart->copy()->endOfMonth();
        
        $payrollNumber = $this->generatePayrollNumber($tenantId, $year, $month);
        
        $payroll = Payroll::create([
            'tenant_id' => $tenantId,
            'payroll_number' => $payrollNumber,
            'year' => $year,
            'month' => $month,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'processed_by' => auth()->id(),
        ]);
        
        return $payroll;
    }
    
    /**
     * Processar folha de pagamento
     */
    public function processPayroll(Payroll $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            $payroll->update(['status' => 'processing']);
            
            // Buscar funcionários ativos do tenant
            $employees = Employee::where('tenant_id', $payroll->tenant_id)
                ->where('status', 'active')
                ->whereHas('activeContract')
                ->get();
            
            $payroll->update(['total_employees' => $employees->count()]);
            
            foreach ($employees as $employee) {
                $this->processEmployeePayroll($payroll, $employee);
            }
            
            // Recalcular totais
            $payroll->calculateTotals();
            
            $payroll->update([
                'status' => 'approved',
                'processed_at' => now(),
            ]);
        });
    }
    
    /**
     * Processar folha individual de funcionário
     */
    public function processEmployeePayroll(Payroll $payroll, Employee $employee): PayrollItem
    {
        $contract = $employee->activeContract;
        
        if (!$contract) {
            throw new \Exception("Funcionário {$employee->full_name} não possui contrato ativo");
        }
        
        // Criar ou atualizar item da folha
        $payrollItem = PayrollItem::updateOrCreate(
            [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ],
            [
                'contract_id' => $contract->id,
                'base_salary' => $contract->base_salary,
                'food_allowance' => $contract->food_allowance,
                'transport_allowance' => $contract->transport_allowance,
                'housing_allowance' => $contract->housing_allowance,
                'worked_days' => $this->getWorkedDays($employee, $payroll->period_start, $payroll->period_end),
                'absence_days' => $this->getAbsenceDays($employee, $payroll->period_start, $payroll->period_end),
            ]
        );
        
        // Calcular valores
        $payrollItem->calculate();
        
        return $payrollItem;
    }
    
    /**
     * Aprovar folha de pagamento
     */
    public function approvePayroll(Payroll $payroll, int $userId): void
    {
        $payroll->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }
    
    /**
     * Marcar folha como paga
     */
    public function markAsPaid(Payroll $payroll, Carbon $paymentDate): void
    {
        DB::transaction(function () use ($payroll, $paymentDate) {
            $payroll->update([
                'status' => 'paid',
                'payment_date' => $paymentDate,
            ]);
            
            $payroll->items()->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });
    }
    
    /**
     * Gerar número de folha
     */
    private function generatePayrollNumber(int $tenantId, int $year, int $month): string
    {
        return sprintf('FP-%04d-%04d-%02d', $tenantId, $year, $month);
    }
    
    /**
     * Obter dias trabalhados
     */
    private function getWorkedDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        // Por padrão, considerar dias úteis do mês
        // Pode ser aprimorado com sistema de presença
        return $start->diffInDaysFiltered(function (Carbon $date) {
            return $date->isWeekday(); // Apenas dias úteis
        }, $end);
    }
    
    /**
     * Obter dias de ausência
     */
    private function getAbsenceDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        return $employee->attendances()
            ->whereBetween('date', [$start, $end])
            ->where('status', 'absent')
            ->count();
    }
    
    /**
     * Gerar recibos de pagamento (PDF)
     */
    public function generatePayslips(Payroll $payroll): array
    {
        $payslips = [];
        
        foreach ($payroll->items as $item) {
            // Implementar geração de recibo em PDF
            // Por enquanto retorna array com dados
            $payslips[] = [
                'employee' => $item->employee->full_name,
                'net_salary' => $item->net_salary,
                'month' => $payroll->month,
                'year' => $payroll->year,
            ];
        }
        
        return $payslips;
    }
}
