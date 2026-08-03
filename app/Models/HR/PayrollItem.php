<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    use HasFactory;

    protected $table = 'hr_payroll_items';

    protected $fillable = [
        'payroll_id', 'employee_id', 'contract_id',
        'base_salary', 'food_allowance', 'transport_allowance',
        'housing_allowance', 'overtime_pay', 'night_shift_pay',
        'night_shift_allowance', 'night_shift_days',
        'holiday_pay', 'commission', 'bonus',
        'family_allowance', 'position_subsidy', 'performance_subsidy',
        'subsidy_13th', 'subsidy_14th',
        'christmas_subsidy_amount', 'vacation_subsidy_amount',
        'other_earnings', 'additional_bonus',
        'gross_salary', 'irt_amount', 'irt_base', 'irt_rate',
        'inss_employee', 'inss_employer', 'inss_base',
        'advance_payment', 'loan_deduction', 'discount_deduction',
        'absence_deduction', 'late_deduction', 'food_deduction',
        'other_deductions', 'total_deductions',
        'net_salary', 'worked_days', 'present_days', 'absence_days',
        'late_days', 'total_working_days',
        'overtime_hours', 'overtime_amount', 'night_hours',
        'calculation_details', 'notes', 'status', 'paid_at',
        'has_christmas_subsidy', 'has_vacation_subsidy',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'calculation_details' => 'array',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
    
    /**
     * Accessors
     */
    public function getTotalAllowancesAttribute()
    {
        return ($this->food_allowance ?? 0) + ($this->transport_allowance ?? 0) + ($this->housing_allowance ?? 0);
    }
    
    public function getTotalBonusesAttribute()
    {
        return ($this->bonus ?? 0) + ($this->overtime_pay ?? 0) + ($this->commission ?? 0);
    }

    /**
     * Recalcular impostos e salário líquido.
     * Não sobrescreve vencimentos/subsídios — apenas INSS, IRT, deduções e net.
     */
    public function calculate()
    {
        $grossSalary = $this->gross_salary ?? 0;
        $foodAllowance = $this->food_allowance ?? 0;
        $transportAllowance = $this->transport_allowance ?? 0;
        $vacationSubsidy = $this->vacation_subsidy_amount ?? 0;

        // Remuneração EFETIVAMENTE auferida = bruto (base completa) − faltas.
        // INSS/IRT incidem sobre o auferido (não sobre a base completa), evitando
        // dupla penalização por faltas (a falta é 1 linha de dedução transparente).
        $absenceDeduction = $this->absence_deduction ?? 0;
        $taxableGross = max(0, $grossSalary - $absenceDeduction);

        // INSS (Decreto 227/18)
        $inssEmployeeRate = (float) \App\Models\HR\HRSetting::get('inss_employee_rate', 3) / 100;
        $inssEmployerRate = (float) \App\Models\HR\HRSetting::get('inss_employer_rate', 8) / 100;
        $inssBase = $taxableGross - $vacationSubsidy;
        $inssEmployee = round($inssBase * $inssEmployeeRate, 2);
        $inssEmployer = round($inssBase * $inssEmployerRate, 2);

        // IRT — isenções alimentação/transporte
        $foodExempt = (float) \App\Models\HR\HRSetting::get('food_tax_exempt', 30000);
        $transportExempt = (float) \App\Models\HR\HRSetting::get('transport_tax_exempt', 30000);
        $foodExemption = min($foodAllowance, $foodExempt);
        $transportExemption = min($transportAllowance, $transportExempt);

        $irtBase = max(0, round($taxableGross - $foodExemption - $transportExemption - $inssEmployee, 2));

        $tenantId = $this->payroll->tenant_id ?? null;
        $irt = \App\Models\HR\IRTTaxBracket::calculateIRT($irtBase, $tenantId);

        if ($irt == 0 && $irtBase > 150000 && function_exists('calculateIRT')) {
            $irtResult = calculateIRT($irtBase);
            $irt = $irtResult['irt_amount'] ?? 0;
        }

        $irtRate = $irtBase > 0 ? round(($irt / $irtBase) * 100, 2) : 0;

        // Total deduções (inclui atraso — descontado no líquido, não na base tributável)
        $totalDeductions = $inssEmployee + $irt
            + ($this->absence_deduction ?? 0)
            + ($this->late_deduction ?? 0)
            + ($this->advance_payment ?? 0)
            + ($this->discount_deduction ?? 0)
            + ($this->food_deduction ?? 0)
            + ($this->loan_deduction ?? 0)
            + ($this->other_deductions ?? 0);

        $netSalary = max(0, round($grossSalary - $totalDeductions, 2));

        $this->update([
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

        return $this;
    }
}
