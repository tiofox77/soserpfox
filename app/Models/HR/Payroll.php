<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'hr_payrolls';

    protected $fillable = [
        'tenant_id', 'payroll_number', 'year', 'month',
        'period_start', 'period_end', 'payment_date',
        'total_gross_salary', 'total_allowances', 'total_bonuses',
        'total_deductions', 'total_irt', 'total_inss_employee',
        'total_inss_employer', 'total_net_salary',
        'total_employees', 'processed_employees',
        'status', 'approved_by', 'approved_at',
        'processed_by', 'processed_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function calculateTotals()
    {
        $items = $this->items;
        
        $this->update([
            'total_gross_salary' => $items->sum('gross_salary'),
            'total_deductions' => $items->sum('total_deductions'),
            'total_irt' => $items->sum('irt_amount'),
            'total_inss_employee' => $items->sum('inss_employee'),
            'total_inss_employer' => $items->sum('inss_employer'),
            'total_net_salary' => $items->sum('net_salary'),
            'processed_employees' => $items->count(),
        ]);
    }
}
