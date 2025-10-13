<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Vacation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_vacations';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'vacation_number',
        'vacation_type',
        'can_split',
        'split_number',
        'total_splits',
        'parent_vacation_id',
        'reference_year',
        'period_start',
        'period_end',
        'entitled_days',
        'working_months',
        'calculated_days',
        'previous_balance',
        'accumulated_days',
        'days_remaining',
        'start_date',
        'end_date',
        'expected_return_date',
        'actual_return_date',
        'returned_on_time',
        'return_notes',
        'requested_days',
        'working_days',
        'daily_rate',
        'vacation_pay',
        'subsidy_amount',
        'total_amount',
        'advance_payment_date',
        'advance_paid',
        'advance_paid_date',
        'advance_paid_by',
        'status',
        'is_active',
        'notes',
        'attachment_path',
        'rejection_reason',
        'cancellation_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'cancelled_by',
        'cancelled_at',
        'replacement_employee_id',
        'paid',
        'paid_date',
        'paid_by',
        'payroll_id',
        'processed_in_payroll',
        'is_collective',
        'collective_group',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
        'advance_payment_date' => 'date',
        'advance_paid_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'paid_date' => 'date',
        'paid' => 'boolean',
        'can_split' => 'boolean',
        'advance_paid' => 'boolean',
        'returned_on_time' => 'boolean',
        'processed_in_payroll' => 'boolean',
        'is_active' => 'boolean',
        'is_collective' => 'boolean',
        'entitled_days' => 'integer',
        'working_months' => 'integer',
        'calculated_days' => 'integer',
        'previous_balance' => 'integer',
        'accumulated_days' => 'integer',
        'days_remaining' => 'integer',
        'split_number' => 'integer',
        'total_splits' => 'integer',
        'requested_days' => 'integer',
        'working_days' => 'integer',
        'daily_rate' => 'decimal:2',
        'vacation_pay' => 'decimal:2',
        'subsidy_amount' => 'decimal:2',
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

    public function replacementEmployee()
    {
        return $this->belongsTo(Employee::class, 'replacement_employee_id');
    }

    public function advancePaidBy()
    {
        return $this->belongsTo(User::class, 'advance_paid_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function parentVacation()
    {
        return $this->belongsTo(Vacation::class, 'parent_vacation_id');
    }

    public function splits()
    {
        return $this->hasMany(Vacation::class, 'parent_vacation_id');
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

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('reference_year', $year);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('vacation_type', $type);
    }

    public function scopeCollective($query)
    {
        return $query->where('is_collective', true);
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="badge bg-warning">Pendente</span>',
            'approved' => '<span class="badge bg-info">Aprovada</span>',
            'rejected' => '<span class="badge bg-danger">Rejeitada</span>',
            'in_progress' => '<span class="badge bg-primary">Em Andamento</span>',
            'completed' => '<span class="badge bg-success">Concluída</span>',
            'cancelled' => '<span class="badge bg-secondary">Cancelada</span>',
            default => '<span class="badge bg-secondary">Indefinido</span>',
        };
    }

    public function getDaysRemainingAttribute()
    {
        if ($this->status !== 'approved' && $this->status !== 'in_progress') {
            return 0;
        }

        $now = now();
        if ($now->lt($this->start_date)) {
            return $this->working_days;
        }

        if ($now->between($this->start_date, $this->end_date)) {
            return $this->end_date->diffInDays($now);
        }

        return 0;
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

    public function markAsPaid($userId)
    {
        $this->update([
            'paid' => true,
            'paid_date' => now(),
            'paid_by' => $userId,
        ]);
    }

    public function start()
    {
        if ($this->status === 'approved') {
            $this->update(['status' => 'in_progress']);
        }
    }

    public function complete()
    {
        if ($this->status === 'in_progress') {
            $this->update(['status' => 'completed']);
        }
    }

    public function cancel($userId, $reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'is_active' => false,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function markAdvanceAsPaid($userId)
    {
        $this->update([
            'advance_paid' => true,
            'advance_paid_date' => now(),
            'advance_paid_by' => $userId,
        ]);
    }

    public function registerReturn(?string $actualReturnDate = null, $notes = null)
    {
        $returnDate = $actualReturnDate ? \Carbon\Carbon::parse($actualReturnDate) : now();
        $onTime = $returnDate->lte($this->expected_return_date ?? $this->end_date);

        $this->update([
            'actual_return_date' => $returnDate,
            'returned_on_time' => $onTime,
            'return_notes' => $notes,
            'status' => 'completed',
        ]);
    }

    public function getVacationTypeNameAttribute()
    {
        return match($this->vacation_type) {
            'normal' => 'Normal',
            'accumulated' => 'Acumulada',
            'advance' => 'Antecipada',
            'collective' => 'Coletiva',
            default => 'Normal',
        };
    }

    public function getIsSplitAttribute()
    {
        return $this->split_number !== null || $this->total_splits > 1;
    }
}

