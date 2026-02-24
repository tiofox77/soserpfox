<?php

namespace App\Services\HR;

use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\HRSetting;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Criar nova folha de pagamento para um mês
     */
    public function createPayroll(int $tenantId, int $year, int $month): Payroll
    {
        return DB::transaction(function () use ($tenantId, $year, $month) {
            $periodStart = Carbon::create($year, $month, 1);
            $periodEnd = $periodStart->copy()->endOfMonth();
            
            $payrollNumber = $this->generatePayrollNumber($tenantId, $year, $month);
            
            // Criar folha
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
            
            // Buscar funcionários ativos com contratos vigentes
            $employees = Employee::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->get();
            
            $processedCount = 0;
            
            foreach ($employees as $employee) {
                // Criar item da folha para cada funcionário
                $this->createPayrollItem($payroll, $employee);
                $processedCount++;
            }
            
            // Atualizar totais
            $payroll->update([
                'total_employees' => $employees->count(),
                'processed_employees' => $processedCount,
            ]);
            
            // Recalcular totais da folha
            $this->recalculatePayrollTotals($payroll);
            
            return $payroll;
        });
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
     * Criar item de folha individual
     */
    private function createPayrollItem(Payroll $payroll, Employee $employee): void
    {
        // Buscar dados de presença do período
        $attendanceData = $this->calculateAttendanceData($employee->id, $payroll->period_start, $payroll->period_end);
        
        // Buscar adiantamentos ativos
        $advanceDeduction = $this->calculateAdvanceDeduction($employee->id, $payroll->period_start);
        
        // Valores base do funcionário
        $baseSalary = $employee->salary ?? 0;
        $foodAllowance = $employee->meal_allowance ?? 0;
        $transportAllowance = $employee->transport_allowance ?? 0;
        $baseBonus = $employee->bonus ?? 0;
        
        // Ajustar salário base com base nos dias trabalhados (deduzir faltas injustificadas)
        $adjustedBaseSalary = $this->calculateProportionalSalary($baseSalary, $attendanceData);
        
        // Ajustar subsídios baseado no método configurado
        $adjustedFoodAllowance = $this->adjustAllowance($foodAllowance, $attendanceData, 'meal');
        $adjustedTransportAllowance = $this->adjustAllowance($transportAllowance, $attendanceData, 'transport');
        
        // Calcular pagamento de horas extras (150% do valor hora)
        $overtimePay = $this->calculateOvertimeBonus($baseSalary, $attendanceData['overtime_hours']);
        
        // Calcular dedução por faltas
        $absenceDeduction = $baseSalary - $adjustedBaseSalary;
        
        // Calcular gross_salary (total de vencimentos)
        $grossSalary = $adjustedBaseSalary + $adjustedFoodAllowance + $adjustedTransportAllowance + $overtimePay + $baseBonus;
        
        $item = PayrollItem::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'base_salary' => $adjustedBaseSalary,
            'food_allowance' => $adjustedFoodAllowance,
            'transport_allowance' => $adjustedTransportAllowance,
            'overtime_pay' => $overtimePay,
            'bonus' => $baseBonus,
            'gross_salary' => $grossSalary,
            
            // Deduções
            'absence_deduction' => $absenceDeduction,
            'advance_payment' => $advanceDeduction,
            'loan_deduction' => 0, // TODO: Implementar quando houver módulo de empréstimos
            'other_deductions' => 0,
            
            // Dados de presença
            'worked_days' => $attendanceData['worked_days'],
            'absence_days' => $attendanceData['unjustified_absences'],
            'overtime_hours' => $attendanceData['overtime_hours'],
            
            'status' => 'pending',
            'notes' => $this->generateAttendanceNotes($attendanceData),
        ]);
        
        // Calcular impostos
        $this->calculateTaxes($item);
    }
    
    /**
     * Calcular dedução de adiantamento para o mês
     */
    private function calculateAdvanceDeduction(int $employeeId, Carbon $periodStart): float
    {
        // Buscar adiantamentos ativos que devem ser deduzidos neste mês
        $advances = SalaryAdvance::where('employee_id', $employeeId)
            ->where('status', 'in_deduction')
            ->where('first_deduction_date', '<=', $periodStart->endOfMonth())
            ->where('balance', '>', 0)
            ->get();
        
        $totalDeduction = 0;
        
        foreach ($advances as $advance) {
            // Verificar se já passou a data da primeira dedução
            if ($advance->first_deduction_date && $periodStart->gte($advance->first_deduction_date)) {
                // Deduzir uma parcela
                $totalDeduction += min($advance->installment_amount, $advance->balance);
            }
        }
        
        return $totalDeduction;
    }
    
    /**
     * Calcular dados de presença do funcionário no período
     */
    private function calculateAttendanceData(int $employeeId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $attendances = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get();
        
        $totalDays = $periodStart->daysInMonth;
        $workingDays = $this->countWorkingDays($periodStart, $periodEnd);
        $workedDays = 0;
        $absences = 0;
        $justifiedAbsences = 0;
        $lateCount = 0;
        $totalLateMinutes = 0;
        $overtimeHours = 0;
        
        foreach ($attendances as $attendance) {
            if ($attendance->status === 'present') {
                $workedDays++;
                $overtimeHours += $attendance->overtime_hours ?? 0;
                
                if ($attendance->is_late) {
                    $lateCount++;
                    $totalLateMinutes += $attendance->late_minutes ?? 0;
                }
            } elseif ($attendance->status === 'absent') {
                $absences++;
                if ($attendance->leave_id) {
                    $justifiedAbsences++;
                }
            }
        }
        
        // CORREÇÃO CRÍTICA: Se não há registros de presença, todos os dias são faltas
        if ($attendances->isEmpty()) {
            $absences = $workingDays;
            $unjustifiedAbsences = $workingDays;
            $justifiedAbsences = 0;
        } else {
            // Se há registros, calcular faltas = dias úteis - dias trabalhados - faltas registradas
            $totalRegistered = $workedDays + $absences;
            if ($totalRegistered < $workingDays) {
                // Dias sem registro são faltas injustificadas
                $missingDays = $workingDays - $totalRegistered;
                $absences += $missingDays;
                $unjustifiedAbsences = $absences - $justifiedAbsences;
            } else {
                $unjustifiedAbsences = $absences - $justifiedAbsences;
            }
        }
        
        return [
            'total_days' => $totalDays,
            'working_days' => $workingDays,
            'worked_days' => $workedDays,
            'absences' => $absences,
            'justified_absences' => $justifiedAbsences,
            'unjustified_absences' => $unjustifiedAbsences,
            'late_count' => $lateCount,
            'total_late_minutes' => $totalLateMinutes,
            'overtime_hours' => $overtimeHours,
            'attendance_rate' => $workingDays > 0 ? ($workedDays / $workingDays) * 100 : 0,
        ];
    }
    
    /**
     * Contar dias úteis no período
     * Usa configuração do sistema ou calcula excluindo fins de semana
     */
    private function countWorkingDays(Carbon $start, Carbon $end): int
    {
        // Buscar configuração de dias úteis por mês
        $configuredWorkingDays = HRSetting::get('working_days_per_month', null);
        
        // Se estiver processando o mês completo e houver configuração, usar ela
        if ($configuredWorkingDays && $start->day === 1 && $end->day === $start->daysInMonth) {
            return (int) $configuredWorkingDays;
        }
        
        // Caso contrário, calcular excluindo fins de semana
        $workingDays = 0;
        $current = $start->copy();
        
        while ($current->lte($end)) {
            // Excluir fins de semana (6 = sábado, 0 = domingo)
            if ($current->dayOfWeek !== 6 && $current->dayOfWeek !== 0) {
                $workingDays++;
            }
            $current->addDay();
        }
        
        return $workingDays;
    }
    
    /**
     * Calcular salário proporcional baseado nos dias trabalhados
     * Funcionário recebe APENAS pelos dias que trabalhou
     */
    private function calculateProportionalSalary(float $baseSalary, array $attendanceData): float
    {
        $workingDays = $attendanceData['working_days'];
        $workedDays = $attendanceData['worked_days'];
        
        // Se não há dias úteis configurados, retorna salário completo (fallback)
        if ($workingDays === 0) {
            return $baseSalary;
        }
        
        // CRÍTICO: Se trabalhou 0 dias, recebe 0 Kz
        if ($workedDays === 0) {
            return 0;
        }
        
        // Calcular salário proporcional aos dias efetivamente trabalhados
        // Exemplo: Trabalhou 1 dia de 26 = recebe 1/26 do salário
        $dailySalary = $baseSalary / $workingDays;
        $proportionalSalary = $dailySalary * $workedDays;
        
        return max(0, $proportionalSalary);
    }
    
    /**
     * Ajustar subsídio baseado no método configurado
     * Métodos disponíveis:
     * - proportional: proporcional aos dias trabalhados (padrão)
     * - full_if_worked: integral se trabalhou pelo menos 1 dia
     * - daily_rate: valor fixo por dia trabalhado
     */
    private function adjustAllowance(float $baseAllowance, array $attendanceData, string $allowanceType = 'meal'): float
    {
        if ($baseAllowance === 0 || $attendanceData['working_days'] === 0) {
            return 0;
        }
        
        // Buscar método de cálculo configurado
        $method = HRSetting::get('allowance_calculation_method', 'proportional');
        $workedDays = $attendanceData['worked_days'];
        $workingDays = $attendanceData['working_days'];
        
        switch ($method) {
            case 'full_if_worked':
                // Se trabalhou pelo menos 1 dia, paga integral
                return $workedDays > 0 ? $baseAllowance : 0;
                
            case 'daily_rate':
                // Valor fixo por dia trabalhado (da configuração)
                $dailyRateKey = $allowanceType === 'meal' ? 'daily_meal_allowance' : 'daily_transport_allowance';
                $dailyRate = HRSetting::get($dailyRateKey, 1000);
                return $dailyRate * $workedDays;
                
            case 'proportional':
            default:
                // Proporcional aos dias trabalhados
                $dailyAllowance = $baseAllowance / $workingDays;
                return $dailyAllowance * $workedDays;
        }
    }
    
    /**
     * Calcular bônus de horas extras
     * Angola: Hora extra = 150% do valor hora normal (50% adicional)
     */
    private function calculateOvertimeBonus(float $baseSalary, float $overtimeHours): float
    {
        if ($overtimeHours === 0) {
            return 0;
        }
        
        // Buscar configuração de dias e horas de trabalho
        $workingDaysPerMonth = HRSetting::get('working_days_per_month', 22);
        $workingHoursPerDay = HRSetting::get('working_hours_per_day', 8);
        $overtimeRate = HRSetting::get('overtime_weekday_rate', 50); // Percentual adicional
        
        // Total de horas no mês
        $totalHoursPerMonth = $workingDaysPerMonth * $workingHoursPerDay;
        
        // Valor por hora
        $hourlyRate = $baseSalary / $totalHoursPerMonth;
        
        // Hora extra = valor hora * (1 + taxa adicional)
        // Exemplo: 50% adicional = 1.5x o valor
        $overtimeMultiplier = 1 + ($overtimeRate / 100);
        
        return $overtimeHours * $hourlyRate * $overtimeMultiplier;
    }
    
    /**
     * Gerar notas de presença para o item
     */
    private function generateAttendanceNotes(array $data): ?string
    {
        $notes = [];
        
        // Informação de dias trabalhados
        $notes[] = sprintf("Dias úteis: %d | Trabalhou: %d", $data['working_days'], $data['worked_days']);
        
        if ($data['unjustified_absences'] > 0) {
            $notes[] = sprintf("Faltas injustificadas: %d (descontadas)", $data['unjustified_absences']);
        }
        
        if ($data['justified_absences'] > 0) {
            $notes[] = sprintf("Faltas justificadas: %d (não descontadas)", $data['justified_absences']);
        }
        
        if ($data['overtime_hours'] > 0) {
            $notes[] = sprintf("Horas extras: %.2fh (+%.0f%%)", $data['overtime_hours'], HRSetting::get('overtime_weekday_rate', 50));
        }
        
        if ($data['late_count'] > 0) {
            $notes[] = sprintf("Atrasos: %d (%d min)", $data['late_count'], $data['total_late_minutes']);
        }
        
        $notes[] = sprintf("Presença: %.1f%%", $data['attendance_rate']);
        
        return implode(' | ', $notes);
    }
    
    /**
     * Calcular impostos (IRT e INSS) - Lei Angolana
     */
    private function calculateTaxes(PayrollItem $item): void
    {
        // Valores já ajustados por faltas (salário proporcional)
        $baseSalary = $item->base_salary;
        $foodAllowance = $item->food_allowance;
        $transportAllowance = $item->transport_allowance;
        $overtimePay = $item->overtime_pay;
        $bonus = $item->bonus;
        
        // ===== CÁLCULO INSS (Decreto 227/18) =====
        // Base INSS = Remuneração Total (tudo que o trabalhador recebe)
        // Inclui: Salário + Todos Subsídios + Horas Extras + Bônus
        // NÃO deduz faltas (base sobre o que foi efetivamente pago)
        $inssBase = $baseSalary + $foodAllowance + $transportAllowance + $overtimePay + $bonus;
        $inssEmployee = $inssBase * 0.03; // 3% do empregado
        $inssEmployer = $inssBase * 0.08; // 8% do empregador
        
        // ===== CÁLCULO IRT (Código IRT 2025) =====
        // Subsídios isentos de IRT (até 30.000 Kz CADA)
        // Apenas o EXCEDENTE de 30k é tributável
        $taxableFoodAllowance = max(0, $foodAllowance - 30000);
        $taxableTransportAllowance = max(0, $transportAllowance - 30000);
        $totalTaxableAllowances = $taxableFoodAllowance + $taxableTransportAllowance;
        
        // Base IRT = Salário + Bônus + Horas Extras + Subsídios Tributáveis - INSS
        $irtBase = $baseSalary + $overtimePay + $bonus + $totalTaxableAllowances - $inssEmployee;
        
        // Usar helper com tabela IRT 2025 actualizada (isenção 100k)
        $irtResult = calculateIRT($irtBase);
        $irt = $irtResult['irt_amount'];
        $irtRate = $irtResult['irt_rate'];
        
        // Total deduções
        $totalDeductions = $inssEmployee + $irt + 
                          ($item->absence_deduction ?? 0) +
                          ($item->advance_payment ?? 0) + 
                          ($item->loan_deduction ?? 0) + 
                          ($item->other_deductions ?? 0);
        
        // Salário líquido = Gross Salary - Deduções
        $netSalary = $item->gross_salary - $totalDeductions;
        
        // Atualizar item
        $item->update([
            'inss_employee' => $inssEmployee,
            'inss_employer' => $inssEmployer,
            'inss_base' => $inssBase,
            'irt_amount' => $irt,
            'irt_base' => $irtBase,
            'irt_rate' => $irtRate,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'status' => 'calculated',
        ]);
    }
    
    /**
     * Recalcular totais da folha
     */
    private function recalculatePayrollTotals(Payroll $payroll): void
    {
        $items = $payroll->items;
        
        $payroll->update([
            'total_gross_salary' => $items->sum('gross_salary'),
            'total_allowances' => $items->sum('total_allowances'),
            'total_bonuses' => $items->sum('total_bonuses'),
            'total_irt' => $items->sum('irt_amount'),
            'total_inss_employee' => $items->sum('inss_employee'),
            'total_inss_employer' => $items->sum('inss_employer'),
            'total_deductions' => $items->sum('total_deductions'),
            'total_net_salary' => $items->sum('net_salary'),
        ]);
    }
    
    /**
     * Recalcular item individual
     */
    public function recalculateItem(PayrollItem $item): void
    {
        $this->calculateTaxes($item);
        $this->recalculatePayrollTotals($item->payroll);
    }
    
    /**
     * Excluir folha de pagamento (apenas se não paga)
     */
    public function deletePayroll(Payroll $payroll): void
    {
        if ($payroll->status === 'paid') {
            throw new \Exception('Não é possível excluir uma folha já paga');
        }
        
        DB::transaction(function () use ($payroll) {
            // Excluir todos os itens da folha
            $payroll->items()->delete();
            
            // Excluir a folha
            $payroll->delete();
        });
    }
    
    /**
     * Processar deduções de adiantamentos após pagamento
     */
    public function processAdvanceDeductions(Payroll $payroll): void
    {
        foreach ($payroll->items as $item) {
            if ($item->advance_payment > 0) {
                // Buscar adiantamentos ativos do funcionário
                $advances = SalaryAdvance::where('employee_id', $item->employee_id)
                    ->where('status', 'in_deduction')
                    ->where('balance', '>', 0)
                    ->orderBy('first_deduction_date')
                    ->get();
                
                $remainingDeduction = $item->advance_payment;
                
                foreach ($advances as $advance) {
                    if ($remainingDeduction <= 0) break;
                    
                    // Calcular quanto deduzir deste adiantamento
                    $deduction = min($advance->installment_amount, $advance->balance, $remainingDeduction);
                    
                    // Registrar o pagamento da parcela
                    $advance->recordInstallmentPayment($deduction);
                    
                    $remainingDeduction -= $deduction;
                }
            }
        }
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
