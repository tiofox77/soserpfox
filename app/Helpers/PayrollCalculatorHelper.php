<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use App\Models\HR\Overtime;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\SalaryDiscount;
use App\Models\HR\Leave;
use App\Models\HR\HRSetting;
use App\Models\HR\IRTTaxBracket;

class PayrollCalculatorHelper
{
    protected Employee $employee;
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected ?int $tenantId;

    // HR Settings (loaded once)
    protected array $settings = [];
    protected bool $workOnSaturday = false;
    protected float $saturdayHours = 4;

    // Calculated values
    protected float $baseSalary = 0;
    protected float $hourlyRate = 0;
    protected float $dailyRate = 0;
    protected int $workingDaysInPeriod = 0;
    protected float $workingHoursPerDay = 8;

    // Attendance data
    protected int $presentDays = 0;
    protected int $absentDays = 0;
    protected int $lateDays = 0;
    protected int $halfDays = 0;
    protected int $leaveDays = 0;
    protected float $totalHoursWorked = 0;

    // Leave data
    protected int $paidLeaveDays = 0;
    protected int $unpaidLeaveDays = 0;

    // Overtime data
    protected float $totalOvertimeAmount = 0;
    protected float $totalOvertimeHours = 0;

    // Night shift data
    protected float $nightShiftAllowance = 0;
    protected int $nightShiftDays = 0;

    // Salary advance/discount deductions
    protected float $totalAdvanceDeduction = 0;
    protected float $totalDiscountDeduction = 0;

    // Override flags
    protected bool $hasChristmasSubsidy = false;
    protected bool $hasVacationSubsidy = false;
    protected float $additionalBonus = 0;

    // Override amounts (set externally)
    protected ?float $overtimeAmountOverride = null;
    protected ?float $nightShiftAllowanceOverride = null;
    protected ?int $nightShiftDaysOverride = null;

    public function __construct(Employee $employee, $startDate, $endDate)
    {
        $this->employee = $employee;
        $this->startDate = Carbon::parse($startDate);
        $this->endDate = Carbon::parse($endDate);

        $user = auth()->user();
        $this->tenantId = $employee->tenant_id
            ?? (method_exists($user, 'activeTenantId') ? $user->activeTenantId() : ($user->tenant_id ?? null));

        $this->loadHRSettings();
        $this->workOnSaturday = (bool) ($this->settings['work_on_saturday'] ?? false);
        $this->saturdayHours = (float) ($this->settings['saturday_working_hours'] ?? 4);
        $this->baseSalary = (float) ($employee->base_salary ?? $employee->salary ?? 0);
        $this->workingDaysInPeriod = self::countWeekdays($this->startDate, $this->endDate, $this->workOnSaturday);
        if ($this->workingDaysInPeriod <= 0) {
            $this->workingDaysInPeriod = (int) ($this->settings['monthly_working_days'] ?? 22);
        }
        $this->workingHoursPerDay = (float) ($this->settings['working_hours_per_day'] ?? $this->settings['daily_work_hours'] ?? 8);
        $this->calculateRates();
    }

    // ─── SETTERS (for batch processing overrides) ───

    public function setChristmasSubsidy(bool $enabled): self
    {
        $this->hasChristmasSubsidy = $enabled;
        return $this;
    }

    public function setVacationSubsidy(bool $enabled): self
    {
        $this->hasVacationSubsidy = $enabled;
        return $this;
    }

    public function setAdditionalBonus(float $amount): self
    {
        $this->additionalBonus = $amount;
        return $this;
    }

    public function setOvertimeAmount(float $amount): self
    {
        $this->overtimeAmountOverride = $amount;
        return $this;
    }

    public function setNightShiftAllowance(float $amount, int $days = 0): self
    {
        $this->nightShiftAllowanceOverride = $amount;
        $this->nightShiftDaysOverride = $days;
        return $this;
    }

    // ─── LOAD ALL DATA ───

    public function loadAllEmployeeData(): self
    {
        $this->loadAttendanceData();
        $this->loadOvertimeData();
        $this->loadSalaryAdvances();
        $this->loadSalaryDiscounts();
        $this->loadLeaveData();
        $this->loadNightShiftData();
        return $this;
    }

    // ─── MAIN CALCULATE ───

    public function calculate(): array
    {
        $transportAllowance = $this->calculateProportionalTransportAllowance();
        $foodAllowance = (float) ($this->employee->food_benefit ?? $this->employee->meal_allowance ?? 0);
        $familyAllowance = (float) ($this->employee->family_allowance ?? 0);
        $positionSubsidy = (float) ($this->employee->position_subsidy ?? 0);
        $performanceSubsidy = (float) ($this->employee->performance_subsidy ?? 0);

        $overtimeAmount = $this->overtimeAmountOverride ?? $this->totalOvertimeAmount;
        $nightAllowance = $this->nightShiftAllowanceOverride ?? $this->nightShiftAllowance;
        $nightDays = $this->nightShiftDaysOverride ?? $this->nightShiftDays;

        // Subsidies
        $christmasSubsidyPct = (float) ($this->settings['christmas_subsidy_percentage'] ?? 50);
        $vacationSubsidyPct = (float) ($this->settings['vacation_subsidy_percentage'] ?? 50);
        $christmasAmount = $this->hasChristmasSubsidy ? round($this->baseSalary * $christmasSubsidyPct / 100, 2) : 0;
        $vacationAmount = $this->hasVacationSubsidy ? round($this->baseSalary * $vacationSubsidyPct / 100, 2) : 0;

        // Deductions for absence/late
        $absenceDeduction = $this->calculateAbsenceDeduction();
        $lateDeduction = $this->calculateLateDeduction();
        $unpaidLeaveDeduction = $this->dailyRate * $this->unpaidLeaveDays;

        // ═══ COLUMN 1: GROSS SALARY ═══
        $grossSalary = $this->baseSalary
            + $transportAllowance
            + $foodAllowance
            + $nightAllowance
            + $overtimeAmount
            + $christmasAmount
            + $vacationAmount
            + $familyAllowance
            + $positionSubsidy
            + $performanceSubsidy
            + $this->additionalBonus
            - $absenceDeduction
            - $unpaidLeaveDeduction;

        $grossSalary = round(max(0, $grossSalary), 2);

        // ═══ INSS ═══
        $inssEmployeeRate = (float) ($this->settings['inss_employee_rate'] ?? 3) / 100;
        $inssEmployerRate = (float) ($this->settings['inss_employer_rate'] ?? 8) / 100;
        // INSS base excludes vacation subsidy (Angolan rule)
        $inssBase = $grossSalary - $vacationAmount;
        $inssEmployee = round($inssBase * $inssEmployeeRate, 2);
        $inssEmployer = round($inssBase * $inssEmployerRate, 2);

        // ═══ COLUMN 2: IRT TAXABLE BASE (MC) ═══
        $foodExempt = (float) ($this->settings['food_tax_exempt'] ?? 30000);
        $transportExempt = (float) ($this->settings['transport_tax_exempt'] ?? 30000);
        $foodExemption = min($foodAllowance, $foodExempt);
        $transportExemption = min($transportAllowance, $transportExempt);

        $irtTaxableBase = $grossSalary - $foodExemption - $transportExemption - $inssEmployee;
        $irtTaxableBase = round(max(0, $irtTaxableBase), 2);

        // IRT calculation from database brackets
        $irtAmount = IRTTaxBracket::calculateIRT($irtTaxableBase, $this->tenantId);

        // ═══ COLUMN 3: NET SALARY ═══
        $advanceDeduction = $this->totalAdvanceDeduction;
        $discountDeduction = $this->totalDiscountDeduction;
        $fundUnion = 0; // not yet implemented
        // Food is deducted from net because provided in-kind (not cash)
        $foodDeduction = $foodAllowance;

        $totalDeductions = $inssEmployee + $irtAmount + $advanceDeduction + $discountDeduction + $fundUnion + $foodDeduction + $lateDeduction;
        $netSalary = round($grossSalary - $totalDeductions, 2);
        $netSalary = max(0, $netSalary);

        return [
            // Employee info
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'period_start' => $this->startDate->toDateString(),
            'period_end' => $this->endDate->toDateString(),

            // Base
            'base_salary' => $this->baseSalary,
            'hourly_rate' => $this->hourlyRate,
            'daily_rate' => $this->dailyRate,

            // Working days
            'total_working_days' => $this->workingDaysInPeriod,
            'present_days' => $this->presentDays,
            'absent_days' => $this->absentDays,
            'late_days' => $this->lateDays,
            'half_days' => $this->halfDays,
            'paid_leave_days' => $this->paidLeaveDays,
            'unpaid_leave_days' => $this->unpaidLeaveDays,
            'days_worked_effectively' => $this->presentDays + $this->lateDays + $this->halfDays,

            // Allowances & Subsidies
            'transport_allowance' => $transportAllowance,
            'food_allowance' => $foodAllowance,
            'family_allowance' => $familyAllowance,
            'position_subsidy' => $positionSubsidy,
            'performance_subsidy' => $performanceSubsidy,
            'night_shift_allowance' => $nightAllowance,
            'night_shift_days' => $nightDays,

            // Overtime
            'overtime_amount' => $overtimeAmount,
            'overtime_hours' => $this->totalOvertimeHours,

            // Subsidies
            'has_christmas_subsidy' => $this->hasChristmasSubsidy,
            'christmas_subsidy_amount' => $christmasAmount,
            'has_vacation_subsidy' => $this->hasVacationSubsidy,
            'vacation_subsidy_amount' => $vacationAmount,
            'additional_bonus' => $this->additionalBonus,

            // Column 1
            'gross_salary' => $grossSalary,

            // Absence/late deductions (part of gross calc)
            'absence_deduction' => $absenceDeduction,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,
            'late_deduction' => $lateDeduction,

            // INSS
            'inss_base' => $inssBase,
            'inss_employee' => $inssEmployee,
            'inss_employer' => $inssEmployer,
            'inss_employee_rate' => $inssEmployeeRate * 100,
            'inss_employer_rate' => $inssEmployerRate * 100,

            // Column 2 - IRT
            'irt_taxable_base' => $irtTaxableBase,
            'food_exemption' => $foodExemption,
            'transport_exemption' => $transportExemption,
            'irt_amount' => $irtAmount,

            // Deductions
            'advance_deduction' => $advanceDeduction,
            'discount_deduction' => $discountDeduction,
            'fund_union' => $fundUnion,
            'food_deduction' => $foodDeduction,
            'total_deductions' => $totalDeductions,

            // Column 3
            'net_salary' => $netSalary,
        ];
    }

    // ─── LOAD HR SETTINGS ───

    protected function loadHRSettings(): void
    {
        $keys = [
            'work_on_saturday', 'saturday_working_hours',
            'working_hours_per_day', 'working_hours_per_week', 'monthly_working_days',
            'daily_work_hours', 'overtime_first_hour_weekday', 'overtime_additional_hours_weekday',
            'overtime_multiplier_weekend', 'overtime_multiplier_holiday',
            'overtime_daily_limit', 'overtime_monthly_limit', 'overtime_yearly_limit',
            'night_shift_percentage', 'night_shift_start_hour', 'night_shift_end_hour',
            'night_shift_multiplier', 'min_overtime_minutes', 'round_to_nearest_minutes',
            'allow_partial_hours', 'inss_employee_rate', 'inss_employer_rate',
            'min_salary_tax_exempt', 'transport_tax_exempt', 'food_tax_exempt',
            'vacation_subsidy_percentage', 'christmas_subsidy_percentage', 'default_hourly_rate',
        ];

        $defaults = [
            'work_on_saturday' => false, 'saturday_working_hours' => 4,
            'working_hours_per_day' => 8, 'working_hours_per_week' => 44,
            'monthly_working_days' => 22, 'daily_work_hours' => 8,
            'overtime_first_hour_weekday' => 1.25, 'overtime_additional_hours_weekday' => 1.375,
            'overtime_multiplier_weekend' => 2.0, 'overtime_multiplier_holiday' => 2.5,
            'overtime_daily_limit' => 2, 'overtime_monthly_limit' => 48,
            'overtime_yearly_limit' => 200, 'night_shift_percentage' => 25,
            'night_shift_start_hour' => 22, 'night_shift_end_hour' => 6,
            'night_shift_multiplier' => 1.25, 'min_overtime_minutes' => 15,
            'round_to_nearest_minutes' => 15, 'allow_partial_hours' => true,
            'inss_employee_rate' => 3, 'inss_employer_rate' => 8,
            'min_salary_tax_exempt' => 70000, 'transport_tax_exempt' => 30000,
            'food_tax_exempt' => 30000, 'vacation_subsidy_percentage' => 50,
            'christmas_subsidy_percentage' => 50, 'default_hourly_rate' => 10.00,
        ];

        foreach ($keys as $key) {
            $this->settings[$key] = HRSetting::get($key, $defaults[$key] ?? null);
        }
    }

    // ─── RATES ───

    protected function calculateRates(): void
    {
        $totalHoursInPeriod = $this->workingDaysInPeriod * $this->workingHoursPerDay;
        $this->hourlyRate = $totalHoursInPeriod > 0 ? round($this->baseSalary / $totalHoursInPeriod, 2) : 0;
        $this->dailyRate = $this->workingDaysInPeriod > 0 ? round($this->baseSalary / $this->workingDaysInPeriod, 2) : 0;
    }

    // ─── ATTENDANCE ───

    public function loadAttendanceData(): void
    {
        $records = Attendance::where('employee_id', $this->employee->id)
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where(function ($q) {
                $q->where('affects_payroll', true)->orWhereNull('affects_payroll');
            })
            ->get();

        $this->presentDays = $records->where('status', 'present')->count();
        $this->lateDays = $records->where('status', 'late')->count();
        $this->halfDays = $records->where('status', 'half_day')->count();
        $this->leaveDays = $records->where('status', 'leave')->count();

        // Leave days count as present
        $this->presentDays += $this->leaveDays;

        $this->totalHoursWorked = (float) $records->sum('hours_worked');

        // Cross-reference with leave management for days without attendance
        $this->crossReferenceLeaves($records);

        // Calculate absent days
        $accountedDays = $this->presentDays + $this->lateDays + $this->halfDays + $this->unpaidLeaveDays;
        $this->absentDays = max(0, $this->workingDaysInPeriod - $accountedDays);

        // If no attendance records at all, assume all present
        if ($records->isEmpty()) {
            $this->presentDays = $this->workingDaysInPeriod;
            $this->absentDays = 0;
        }
    }

    protected function crossReferenceLeaves($attendanceRecords): void
    {
        $attendanceDates = $attendanceRecords->pluck('date')->map(fn($d) => $d->toDateString())->toArray();

        $leaves = Leave::where('employee_id', $this->employee->id)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereBetween('start_date', [$this->startDate, $this->endDate])
                  ->orWhereBetween('end_date', [$this->startDate, $this->endDate])
                  ->orWhere(function ($q2) {
                      $q2->where('start_date', '<=', $this->startDate)
                         ->where('end_date', '>=', $this->endDate);
                  });
            })
            ->get();

        foreach ($leaves as $leave) {
            $leaveStart = max($leave->start_date, $this->startDate);
            $leaveEnd = min($leave->end_date, $this->endDate);
            $current = Carbon::parse($leaveStart);
            $end = Carbon::parse($leaveEnd);

            while ($current->lte($end)) {
                $isWorkDay = $current->isWeekday() || ($this->workOnSaturday && $current->isSaturday());
                if ($isWorkDay && !in_array($current->toDateString(), $attendanceDates)) {
                    $isPaid = !in_array($leave->type, ['unpaid', 'unpaid_leave']);
                    if ($isPaid) {
                        $this->paidLeaveDays++;
                        $this->presentDays++;
                    } else {
                        $this->unpaidLeaveDays++;
                    }
                }
                $current->addDay();
            }
        }
    }

    // ─── OVERTIME ───

    public function loadOvertimeData(): void
    {
        $records = Overtime::where('employee_id', $this->employee->id)
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('is_night_shift', false)->orWhereNull('is_night_shift');
            })
            ->get();

        $this->totalOvertimeAmount = (float) $records->sum(fn($r) => $r->amount ?? $r->total_amount ?? 0);
        $this->totalOvertimeHours = (float) $records->sum(fn($r) => $r->total_hours ?? $r->direct_hours ?? 0);
    }

    // ─── NIGHT SHIFT ───

    public function loadNightShiftData(): void
    {
        $records = Overtime::where('employee_id', $this->employee->id)
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('status', 'approved')
            ->where('is_night_shift', true)
            ->get();

        if ($records->isNotEmpty()) {
            $this->nightShiftAllowance = (float) $records->sum(fn($r) => $r->amount ?? $r->total_amount ?? 0);
            $this->nightShiftDays = (int) $records->sum('direct_hours');
        } else {
            // Auto-calculate from night shift percentage if no specific records
            $nightPct = (float) ($this->settings['night_shift_percentage'] ?? 25) / 100;
            $this->nightShiftAllowance = round($this->dailyRate * $this->nightShiftDays * $nightPct, 2);
        }
    }

    // ─── SALARY ADVANCES ───

    public function loadSalaryAdvances(): void
    {
        $advances = SalaryAdvance::where('employee_id', $this->employee->id)
            ->where('tenant_id', $this->tenantId)
            ->whereIn('status', ['approved', 'in_deduction', 'paid'])
            ->where(function ($q) {
                $q->where('remaining_installments', '>', 0)
                  ->orWhere(function ($q2) {
                      $q2->whereColumn('installments_paid', '<', 'installments');
                  });
            })
            ->get();

        $this->totalAdvanceDeduction = (float) $advances->sum('installment_amount');
    }

    // ─── SALARY DISCOUNTS ───

    public function loadSalaryDiscounts(): void
    {
        $discounts = SalaryDiscount::where('employee_id', $this->employee->id)
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'approved')
            ->where('remaining_installments', '>', 0)
            ->get();

        $this->totalDiscountDeduction = (float) $discounts->sum('installment_amount');
    }

    // ─── LEAVE DATA ───

    public function loadLeaveData(): void
    {
        // Already handled in crossReferenceLeaves within loadAttendanceData
    }

    // ─── TRANSPORT ALLOWANCE (Proportional) ───

    protected function calculateProportionalTransportAllowance(): float
    {
        $transport = (float) ($this->employee->transport_benefit ?? $this->employee->transport_allowance ?? 0);
        if ($transport <= 0 || $this->workingDaysInPeriod <= 0) return 0;

        // Transport is proportional to days effectively worked (not leave days)
        $effectiveDays = $this->presentDays + $this->lateDays + $this->halfDays - $this->paidLeaveDays;
        $effectiveDays = max(0, $effectiveDays);

        return round(($transport / $this->workingDaysInPeriod) * $effectiveDays, 2);
    }

    // ─── ABSENCE DEDUCTION ───

    protected function calculateAbsenceDeduction(): float
    {
        return round($this->dailyRate * $this->absentDays, 2);
    }

    // ─── LATE DEDUCTION ───

    protected function calculateLateDeduction(): float
    {
        // Each late arrival deducts 1 hour
        return round($this->hourlyRate * $this->lateDays, 2);
    }

    // ─── STATIC: Count weekdays ───

    public static function countWeekdays(Carbon $start, Carbon $end, bool $includeSaturday = false): int
    {
        $count = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if ($current->isWeekday() || ($includeSaturday && $current->isSaturday())) {
                $count++;
            }
            $current->addDay();
        }
        return $count;
    }

    public function worksSaturday(): bool
    {
        return $this->workOnSaturday;
    }

    public function getSaturdayHours(): float
    {
        return $this->saturdayHours;
    }

    // ─── STATIC: Angolan Holidays ───

    public static function getAngolanHolidays(int $year): array
    {
        return [
            "$year-01-01", // Ano Novo
            "$year-02-04", // Dia da Paz
            "$year-03-08", // Dia Internacional da Mulher
            "$year-04-04", // Dia da Paz e Reconciliação Nacional
            "$year-05-01", // Dia do Trabalhador
            "$year-09-17", // Dia dos Heróis Nacionais
            "$year-11-02", // Dia dos Finados
            "$year-11-11", // Dia da Independência Nacional
            "$year-12-25", // Natal
        ];
    }

    public static function isAngolanHoliday(Carbon $date): bool
    {
        $holidays = self::getAngolanHolidays($date->year);
        return in_array($date->toDateString(), $holidays);
    }

    // ─── GETTERS (for external access) ───

    public function getHourlyRate(): float { return $this->hourlyRate; }
    public function getDailyRate(): float { return $this->dailyRate; }
    public function getWorkingDays(): int { return $this->workingDaysInPeriod; }
    public function getBaseSalary(): float { return $this->baseSalary; }
    public function getSettings(): array { return $this->settings; }
}
