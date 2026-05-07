<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'hr_attendances';

    protected $fillable = [
        'tenant_id', 'employee_id', 'shift_id', 'leave_id', 'date', 'check_in', 'check_out',
        'time_in', 'time_out', 'hours_worked', 'overtime_hours', 'is_late', 'late_minutes',
        'hourly_rate', 'affects_payroll', 'status', 'notes', 'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'is_late' => 'boolean',
        'affects_payroll' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function leave()
    {
        return $this->belongsTo(Leave::class);
    }
}
