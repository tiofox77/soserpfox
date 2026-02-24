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
        'hours_worked', 'overtime_hours', 'is_late', 'late_minutes', 'status', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'is_late' => 'boolean',
    ];

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
