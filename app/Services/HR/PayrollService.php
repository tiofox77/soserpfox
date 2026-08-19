<?php

namespace App\Services\HR;

use App\Exceptions\HR\FolhaJaExiste;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\SalaryDiscount;
use App\Models\HR\Overtime;
use App\Models\HR\IRTTaxBracket;
use App\Models\HR\HRSetting;
use App\Helpers\PayrollCalculatorHelper;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Criar nova folha de pagamento para um mês
     */
    public function createPayroll(int $tenantId, int $year, int $month): Payroll
    {
        // Já existe folha deste mês?
        //
        // Há um índice único (tenant_id, year, month) — e sem esta verificação
        // a segunda tentativa rebentava com o erro cru da base a ser mostrado
        // ao utilizador: "Duplicate entry '70-2026-8' for key
        // hr_payrolls_tenant_id_year_month_unique". Quem está do outro lado
        // não tem como perceber que o problema é a folha já existir.
        $existente = Payroll::where('tenant_id', $tenantId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existente) {
            $mes = Carbon::create($year, $month, 1)->translatedFormat('F \d\e Y');

            throw new FolhaJaExiste(
                "Já existe uma folha de {$mes} nesta empresa ({$existente->payroll_number}, "
                . "estado: {$existente->status}). Abra essa folha em vez de criar outra — "
                . 'para recomeçar, elimine-a primeiro.',
                $existente
            );
        }

        try {
            return $this->gravarFolha($tenantId, $year, $month);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // A verificação acima tem janela de corrida: dois cliques ao mesmo
            // tempo passam ambos. Apanha-se a violação do índice único e
            // devolve-se a MESMA mensagem — o utilizador não tem de saber que
            // houve uma corrida, só que a folha já existe.
            $folha = Payroll::where('tenant_id', $tenantId)
                ->where('year', $year)->where('month', $month)->first();

            $mes = Carbon::create($year, $month, 1)->translatedFormat('F \d\e Y');

            throw new FolhaJaExiste(
                "Já existe uma folha de {$mes} nesta empresa"
                . ($folha ? " ({$folha->payroll_number})" : '')
                . '. Abra essa folha em vez de criar outra.',
                $folha
            );
        }
    }

    private function gravarFolha(int $tenantId, int $year, int $month): Payroll
    {
        return DB::transaction(function () use ($tenantId, $year, $month) {
            $periodStart = Carbon::create($year, $month, 1);
            $periodEnd = $periodStart->copy()->endOfMonth();

            $payrollNumber = $this->generatePayrollNumber($tenantId, $year, $month);

            // Criar folha. A verificacao acima tem janela de corrida (dois
            // cliques ao mesmo tempo passam ambos), por isso a violacao do
            // indice unico e apanhada e convertida na mesma mensagem — o
            // mesmo padrao usado no checkout do restaurante e no POS.
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
        if (!$employee->activeContract) {
            throw new \Exception("Funcionário {$employee->full_name} não possui contrato ativo");
        }

        // Motor único: createPayrollItem constrói o item completo (vencimentos + deduções,
        // presença/licença/atraso) e calcula os impostos. Idempotente (updateOrCreate).
        return $this->createPayrollItem($payroll, $employee);
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

        // Integração Folha → Contabilidade (opt-in por tenant). Nunca deve partir a
        // aprovação da folha: falhas de contabilidade são registadas e ignoradas.
        try {
            $tenant = \App\Models\Tenant::find($payroll->tenant_id);
            if ($tenant && ($tenant->accounting_integration_enabled ?? false)) {
                (new \App\Services\Accounting\PostingService())->postPayroll($payroll);
            }
        } catch (\Throwable $e) {
            \Log::warning('Folha→Contabilidade: lançamento não gerado', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage(),
            ]);
        }
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
        $workOnSaturday = (bool) HRSetting::get('work_on_saturday', false);
        return $start->diffInDaysFiltered(function (Carbon $date) use ($workOnSaturday) {
            return $date->isWeekday() || ($workOnSaturday && $date->isSaturday());
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
    private function createPayrollItem(Payroll $payroll, Employee $employee): PayrollItem
    {
        // Buscar dados de presença do período (ciente de licença paga/não-paga + atraso)
        $attendanceData = $this->calculateAttendanceData($employee->id, $payroll->period_start, $payroll->period_end);

        // Buscar adiantamentos e descontos ativos
        $advanceDeduction = $this->calculateAdvanceDeduction($employee->id, $payroll->period_start);
        $discountDeduction = $this->calculateDiscountDeduction($employee->id);

        // Salário base: preferir o do contrato ativo, senão o do funcionário
        $contract = $employee->activeContract;
        $baseSalary = (float) ($contract->base_salary ?? $employee->base_salary ?? $employee->salary ?? 0);

        // Subsídios de transporte/alimentação: preferir o campo do funcionário; senão o global (settings)
        $empFood = (float) ($employee->food_benefit ?? 0);
        $foodAllowance = $empFood > 0 ? $empFood
            : ((bool) HRSetting::get('meal_allowance_enabled', true)
                ? (float) HRSetting::get('monthly_food_allowance', HRSetting::get('meal_allowance', 0)) : 0);
        $empTransport = (float) ($employee->transport_benefit ?? 0);
        $transportAllowance = $empTransport > 0 ? $empTransport
            : ((bool) HRSetting::get('transport_allowance_enabled', true)
                ? (float) HRSetting::get('monthly_transport_allowance', HRSetting::get('transport_allowance', 0)) : 0);

        $baseBonus = (float) ($employee->bonus ?? 0);
        $familyAllowance = (float) ($employee->family_allowance ?? 0);
        $positionSubsidy = (float) ($employee->position_subsidy ?? 0);
        $performanceSubsidy = (float) ($employee->performance_subsidy ?? 0);

        // Salário proporcional aos dias pagos (presença + licença paga); falta = dedução transparente
        $adjustedBaseSalary = $this->calculateProportionalSalary($baseSalary, $attendanceData);
        $absenceDeduction = round($baseSalary - $adjustedBaseSalary, 2);

        // Subsídios proporcionais aos dias efetivamente trabalhados (exclui licença/férias)
        $adjustedFoodAllowance = $this->adjustAllowance($foodAllowance, $attendanceData, 'meal');
        $adjustedTransportAllowance = $this->adjustAllowance($transportAllowance, $attendanceData, 'transport');

        // Dedução de atraso (descontada no líquido): horas de atraso × taxa horária
        $hoursPerDay = (float) HRSetting::get('working_hours_per_day', 8);
        $workingDays = (int) ($attendanceData['working_days'] ?: 1);
        $hourlyRate = ($hoursPerDay > 0 && $workingDays > 0) ? $baseSalary / ($workingDays * $hoursPerDay) : 0;
        $lateMinutes = (float) ($attendanceData['total_late_minutes'] ?? 0);
        $lateCount = (int) ($attendanceData['late_count'] ?? 0);
        $lateHours = $lateMinutes > 0 ? $lateMinutes / 60 : $lateCount; // fallback: 1h por atraso
        $lateDeduction = round($hourlyRate * $lateHours, 2);

        // Horas extras e turno noturno aprovados do período
        $overtimeData = $this->getApprovedOvertimeData($employee->id, $payroll->tenant_id, $payroll->period_start, $payroll->period_end);
        $overtimePay = $overtimeData['amount'];
        $overtimeHours = $overtimeData['hours'];
        $nightShiftData = $this->getApprovedNightShiftData($employee->id, $payroll->tenant_id, $payroll->period_start, $payroll->period_end);

        // Subsídios 13º (Natal) / férias — auto-detect
        $hasChristmas = ((int) $payroll->month === (int) HRSetting::get('christmas_bonus_month', 12));
        $christmasPct = (float) HRSetting::get('christmas_subsidy_percentage', 50) / 100;
        $christmasAmount = $hasChristmas ? round($baseSalary * $christmasPct, 2) : 0;
        $hasVacation = $employee->hire_date ? ((int) $employee->hire_date->month === (int) $payroll->month) : false;
        $vacationPct = (float) HRSetting::get('vacation_subsidy_percentage', 50) / 100;
        $vacationAmount = $hasVacation ? round($baseSalary * $vacationPct, 2) : 0;

        // Alimentação em espécie? (default: paga em dinheiro → food_deduction 0).
        // Se food_paid_in_kind=true, entra no bruto (imposto) mas é subtraída no líquido.
        $foodPaidInKind = (bool) HRSetting::get('food_paid_in_kind', false);
        $foodDeduction = $foodPaidInKind ? $adjustedFoodAllowance : 0;

        // gross_salary usa o salário base COMPLETO; a falta é deduzida UMA vez via absence_deduction
        // (INSS/IRT incidem sobre o auferido = bruto − faltas, ver PayrollItem::calculate()).
        $grossSalary = $baseSalary + $adjustedFoodAllowance + $adjustedTransportAllowance
            + $overtimePay + $baseBonus + $familyAllowance + $positionSubsidy + $performanceSubsidy
            + $nightShiftData['amount'] + $christmasAmount + $vacationAmount;

        $item = PayrollItem::updateOrCreate(
            [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ],
            [
                'contract_id' => $contract->id ?? null,
                'base_salary' => $baseSalary,
                'food_allowance' => $adjustedFoodAllowance,
                'transport_allowance' => $adjustedTransportAllowance,
                'housing_allowance' => $contract->housing_allowance ?? 0,
                'overtime_pay' => $overtimePay,
                'overtime_hours' => $overtimeHours,
                'overtime_amount' => $overtimePay,
                'bonus' => $baseBonus,
                'family_allowance' => $familyAllowance,
                'position_subsidy' => $positionSubsidy,
                'performance_subsidy' => $performanceSubsidy,
                'night_shift_pay' => $nightShiftData['amount'],
                'night_shift_allowance' => $nightShiftData['amount'],
                'night_shift_days' => $nightShiftData['days'],
                'has_christmas_subsidy' => $hasChristmas,
                'christmas_subsidy_amount' => $christmasAmount,
                'has_vacation_subsidy' => $hasVacation,
                'vacation_subsidy_amount' => $vacationAmount,
                'gross_salary' => $grossSalary,

                // Deduções
                'absence_deduction' => $absenceDeduction,
                'late_deduction' => $lateDeduction,
                'advance_payment' => $advanceDeduction,
                'discount_deduction' => $discountDeduction,
                'food_deduction' => $foodDeduction,
                'loan_deduction' => 0,
                'other_deductions' => 0,

                // Dados de presença
                'worked_days' => $attendanceData['worked_days'],
                'present_days' => $attendanceData['paid_days'],
                'absence_days' => $attendanceData['unjustified_absences'],
                'late_days' => $lateCount,
                'total_working_days' => $attendanceData['working_days'],
                'night_hours' => $nightShiftData['days'] * $hoursPerDay,

                'status' => 'pending',
                'notes' => $this->generateAttendanceNotes($attendanceData),
            ]
        );

        // Calcular impostos (motor único)
        $this->calculateTaxes($item);

        return $item;
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
        $workOnSaturday = (bool) HRSetting::get('work_on_saturday', false);
        $workingDays = $this->countWorkingDays($periodStart, $periodEnd);

        // Presenças do período, indexadas por dia (modelo real: status present/absent + is_late + leave_id)
        $attendances = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->date)->toDateString());
        $hasAnyRecord = $attendances->isNotEmpty();

        // Licenças APROVADAS que se sobrepõem ao período (paid = paga / não-paga)
        $leaves = \App\Models\HR\Leave::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get();
        $leaveForDate = function (Carbon $d) use ($leaves) {
            foreach ($leaves as $lv) {
                if ($d->betweenIncluded(Carbon::parse($lv->start_date), Carbon::parse($lv->end_date))) {
                    return $lv;
                }
            }
            return null;
        };

        // Sem QUALQUER registo de presença no período → comportamento configurável.
        // Default (assume_present_without_attendance=true): empresa que não usa Assiduidade
        // assume presença total nos dias sem licença (evita salários a 0). As licenças
        // aprovadas continuam a ser tratadas dia-a-dia mesmo neste modo.
        $assumePresent = !$hasAnyRecord && (bool) HRSetting::get('assume_present_without_attendance', true);

        $worked = 0; $paidLeave = 0; $unpaidLeave = 0;
        $justifiedAbsences = 0; $unjustifiedAbsences = 0;
        $lateCount = 0; $totalLateMinutes = 0; $overtimeHours = 0;

        $cursor = $periodStart->copy();
        while ($cursor->lte($periodEnd)) {
            $isWorkDay = $cursor->isWeekday() || ($workOnSaturday && $cursor->isSaturday());
            if ($isWorkDay) {
                $att = $attendances->get($cursor->toDateString());
                $lv = $leaveForDate($cursor);

                if ($att && $att->status === 'present') {
                    $worked++;
                    $overtimeHours += $att->overtime_hours ?? 0;
                    if ($att->is_late) { $lateCount++; $totalLateMinutes += $att->late_minutes ?? 0; }
                } elseif ($lv) {
                    // Licença aprovada SOBREPÕE falta: paga → conta como paga; não paga → deduzida
                    if ($lv->paid) { $paidLeave++; } else { $unpaidLeave++; }
                    $justifiedAbsences++;
                } elseif ($att && $att->status === 'absent') {
                    $unjustifiedAbsences++;
                } else {
                    // Sem registo e sem licença
                    if ($assumePresent) { $worked++; } else { $unjustifiedAbsences++; }
                }
            }
            $cursor->addDay();
        }

        $paidDays = $worked + $paidLeave;                         // pagos (presença + licença paga)
        $deductibleAbsences = $unjustifiedAbsences + $unpaidLeave; // descontados

        return [
            'total_days' => $periodStart->daysInMonth,
            'working_days' => $workingDays,
            'worked_days' => $worked,               // só presença efetiva (subsídios de transporte/alimentação)
            'paid_days' => $paidDays,               // presença + licença paga (base salarial)
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
            'absences' => $deductibleAbsences,
            'justified_absences' => $justifiedAbsences,
            'unjustified_absences' => $unjustifiedAbsences,
            'deductible_absences' => $deductibleAbsences,
            'late_count' => $lateCount,
            'total_late_minutes' => $totalLateMinutes,
            'overtime_hours' => $overtimeHours,
            'attendance_rate' => $workingDays > 0 ? ($paidDays / $workingDays) * 100 : 0,
        ];
    }
    
    /**
     * Contar dias úteis no período
     * Usa configuração do sistema ou calcula excluindo fins de semana
     */
    private function countWorkingDays(Carbon $start, Carbon $end): int
    {
        $workOnSaturday = (bool) HRSetting::get('work_on_saturday', false);

        // Buscar configuração de dias úteis por mês
        $configuredWorkingDays = HRSetting::get('working_days_per_month', null);
        
        // Se estiver processando o mês completo e houver configuração, usar ela
        if ($configuredWorkingDays && $start->day === 1 && $end->day === $start->daysInMonth) {
            return (int) $configuredWorkingDays;
        }
        
        // Caso contrário, calcular dinamicamente
        $workingDays = 0;
        $current = $start->copy();
        
        while ($current->lte($end)) {
            if ($current->isWeekday() || ($workOnSaturday && $current->isSaturday())) {
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
        // Dias PAGOS = presença efetiva + licença paga (a licença paga NÃO é descontada)
        $paidDays = $attendanceData['paid_days'] ?? $attendanceData['worked_days'];

        // Se não há dias úteis configurados, retorna salário completo (fallback)
        if ($workingDays === 0) {
            return $baseSalary;
        }

        // Se não há dias pagos (0 presença e 0 licença paga), recebe 0 Kz
        if ($paidDays === 0) {
            return 0;
        }

        // Salário proporcional aos dias pagos (ex.: 1 dia de 26 = 1/26 do salário)
        $dailySalary = $baseSalary / $workingDays;
        $proportionalSalary = $dailySalary * $paidDays;

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
    /**
     * Calcular impostos e líquido — MOTOR ÚNICO.
     * Delega em PayrollItem::calculate() para não duplicar a matemática de INSS/IRT/net.
     * (As componentes de vencimento e deduções já foram gravadas por createPayrollItem.)
     */
    private function calculateTaxes(PayrollItem $item): void
    {
        $item->calculate();
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
     * Calcular dedução de descontos salariais para o mês
     */
    private function calculateDiscountDeduction(int $employeeId): float
    {
        $discounts = SalaryDiscount::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('remaining_installments', '>', 0)
            ->get();

        return (float) $discounts->sum('installment_amount');
    }

    /**
     * Buscar horas extras aprovadas do período (excluindo noturno)
     */
    private function getApprovedOvertimeData(int $employeeId, int $tenantId, Carbon $start, Carbon $end): array
    {
        $records = Overtime::where('employee_id', $employeeId)
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('is_night_shift', false)->orWhereNull('is_night_shift');
            })
            ->get();

        return [
            'amount' => (float) $records->sum(fn($r) => $r->amount ?? $r->total_amount ?? 0),
            'hours' => (float) $records->sum(fn($r) => $r->total_hours ?? $r->direct_hours ?? 0),
        ];
    }

    /**
     * Buscar dados de turno noturno aprovados do período
     */
    private function getApprovedNightShiftData(int $employeeId, int $tenantId, Carbon $start, Carbon $end): array
    {
        $records = Overtime::where('employee_id', $employeeId)
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->where('status', 'approved')
            ->where('is_night_shift', true)
            ->get();

        return [
            'amount' => (float) $records->sum(fn($r) => $r->amount ?? $r->total_amount ?? 0),
            'days' => (int) $records->sum('direct_hours'),
        ];
    }

    /**
     * Processar deduções de descontos salariais após pagamento
     */
    public function processDiscountDeductions(Payroll $payroll): void
    {
        foreach ($payroll->items as $item) {
            if (($item->discount_deduction ?? 0) > 0) {
                $discounts = SalaryDiscount::where('employee_id', $item->employee_id)
                    ->where('status', 'approved')
                    ->where('remaining_installments', '>', 0)
                    ->orderBy('created_at')
                    ->get();

                foreach ($discounts as $discount) {
                    $discount->registerPayment();
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
