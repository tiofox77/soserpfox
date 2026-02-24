<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_shifts';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'start_time',
        'end_time',
        'hours_per_day',
        'work_days',
        'color',
        'is_night_shift',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'work_days' => 'array',
        'hours_per_day' => 'decimal:2',
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get work days as formatted string
     */
    public function getWorkDaysFormattedAttribute()
    {
        if (!$this->work_days) {
            return 'Não definido';
        }

        $days = [
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        return collect($this->work_days)
            ->map(fn($day) => $days[$day] ?? '')
            ->filter()
            ->implode(', ');
    }

    /**
     * Get employees in this shift
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'shift_id');
    }

    /**
     * Get attendances using this shift
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'shift_id');
    }
}
