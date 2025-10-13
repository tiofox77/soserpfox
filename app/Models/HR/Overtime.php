<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Overtime extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_overtime';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'attendance_id',
        'overtime_number',
        'date',
        'start_time',
        'end_time',
        'total_hours',
        'overtime_type',
        'multiplier',
        'hourly_rate',
        'overtime_rate',
        'total_amount',
        'description',
        'notes',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'paid',
        'paid_date',
        'paid_by',
        'payroll_id',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_date' => 'date',
        'paid' => 'boolean',
        'total_hours' => 'decimal:2',
        'multiplier' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('overtime_type', $type);
    }

    // Accessors
    public function getOvertimeTypeNameAttribute()
    {
        return match($this->overtime_type) {
            'weekday' => 'Dia Útil (50%)',
            'weekend' => 'Fim de Semana (100%)',
            'holiday' => 'Feriado (100%)',
            'night' => 'Noturno (25%)',
            default => 'Indefinido',
        };
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="badge bg-warning">Pendente</span>',
            'approved' => '<span class="badge bg-info">Aprovado</span>',
            'rejected' => '<span class="badge bg-danger">Rejeitado</span>',
            'paid' => '<span class="badge bg-success">Pago</span>',
            'cancelled' => '<span class="badge bg-secondary">Cancelado</span>',
            default => '<span class="badge bg-secondary">Indefinido</span>',
        };
    }

    // Methods
    public function approve($userId)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject($userId, $reason = null)
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markAsPaid($userId, $payrollId = null)
    {
        $this->update([
            'status' => 'paid',
            'paid' => true,
            'paid_date' => now(),
            'paid_by' => $userId,
            'payroll_id' => $payrollId,
        ]);
    }

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}
