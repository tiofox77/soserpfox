<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $table = 'hr_contracts';

    protected $fillable = [
        'tenant_id', 'employee_id', 'contract_number', 'contract_type',
        'start_date', 'end_date', 'trial_period_end',
        'base_salary', 'food_allowance', 'transport_allowance',
        'housing_allowance', 'other_allowances', 'payment_frequency',
        'weekly_hours', 'work_start_time', 'work_end_time',
        'has_health_insurance', 'has_life_insurance', 'vacation_days_per_year',
        'subject_to_irt', 'subject_to_inss', 'irt_percentage',
        'contract_file', 'status', 'termination_reason', 'termination_date', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'trial_period_end' => 'date',
        'termination_date' => 'date',
        'has_health_insurance' => 'boolean',
        'has_life_insurance' => 'boolean',
        'subject_to_irt' => 'boolean',
        'subject_to_inss' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getTotalCompensation()
    {
        return $this->base_salary + $this->food_allowance + 
               $this->transport_allowance + $this->housing_allowance + 
               $this->other_allowances;
    }

    public function isActive()
    {
        return $this->status === 'active';
    }
}
