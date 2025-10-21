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
        'holiday_pay', 'commission', 'bonus',
        'subsidy_13th', 'subsidy_14th', 'other_earnings',
        'gross_salary', 'irt_amount', 'irt_base', 'irt_rate',
        'inss_employee', 'inss_employer', 'inss_base',
        'advance_payment', 'loan_deduction', 'absence_deduction',
        'late_deduction', 'other_deductions', 'total_deductions',
        'net_salary', 'worked_days', 'absence_days',
        'overtime_hours', 'night_hours', 'calculation_details',
        'notes', 'status', 'paid_at',
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
     * Calcular folha de pagamento do funcionário
     */
    public function calculate()
    {
        $contract = $this->contract ?? $this->employee->activeContract;
        
        if (!$contract) {
            throw new \Exception('Funcionário não possui contrato ativo');
        }

        // Calcular salário líquido usando helper
        $calculation = calculateNetSalary(
            $contract->base_salary,
            [
                'food' => $contract->food_allowance,
                'transport' => $contract->transport_allowance,
                'housing' => $contract->housing_allowance,
                'other' => $this->other_earnings,
            ],
            [
                'advance' => $this->advance_payment,
                'loan' => $this->loan_deduction,
                'absence' => $this->absence_deduction,
                'other' => $this->other_deductions,
            ]
        );

        // Atualizar campos
        $this->update([
            'base_salary' => $calculation['base_salary'],
            'food_allowance' => $calculation['food_allowance'],
            'transport_allowance' => $calculation['transport_allowance'],
            'housing_allowance' => $calculation['housing_allowance'],
            'gross_salary' => $calculation['total_gross'],
            'inss_employee' => $calculation['inss_employee'],
            'inss_employer' => $calculation['inss_employer'],
            'inss_base' => $calculation['inss_base'],
            'irt_amount' => $calculation['irt_amount'],
            'irt_base' => $calculation['irt_base'],
            'irt_rate' => $calculation['irt_rate'],
            'total_deductions' => $calculation['total_deductions'],
            'net_salary' => $calculation['net_salary'],
            'calculation_details' => $calculation,
            'status' => 'calculated',
        ]);

        return $this;
    }
}
