<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class SalaryAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_salary_advances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'advance_number',
        'requested_amount',
        'approved_amount',
        'base_salary',
        'max_allowed',
        'installments',
        'installment_amount',
        'installments_paid',
        'balance',
        'request_date',
        'payment_date',
        'first_deduction_date',
        'reason',
        'notes',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'payment_date' => 'date',
        'first_deduction_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'max_allowed' => 'decimal:2',
        'installments' => 'integer',
        'installment_amount' => 'decimal:2',
        'installments_paid' => 'integer',
        'balance' => 'decimal:2',
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

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeInDeduction($query)
    {
        return $query->where('status', 'in_deduction');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="badge bg-warning">Pendente</span>',
            'approved' => '<span class="badge bg-info">Aprovado</span>',
            'rejected' => '<span class="badge bg-danger">Rejeitado</span>',
            'paid' => '<span class="badge bg-success">Pago</span>',
            'in_deduction' => '<span class="badge bg-primary">Em Dedução</span>',
            'completed' => '<span class="badge bg-success">Concluído</span>',
            'cancelled' => '<span class="badge bg-secondary">Cancelado</span>',
            default => '<span class="badge bg-secondary">Indefinido</span>',
        };
    }

    public function getRemainingInstallmentsAttribute()
    {
        return $this->installments - $this->installments_paid;
    }

    // Methods
    public function approve($userId, $approvedAmount = null)
    {
        $amount = $approvedAmount ?? $this->requested_amount;
        
        $this->update([
            'status' => 'approved',
            'approved_amount' => $amount,
            'approved_by' => $userId,
            'approved_at' => now(),
            'balance' => $amount,
            'installment_amount' => round($amount / $this->installments, 2),
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
            'status' => 'paid',
            'paid_by' => $userId,
            'paid_at' => now(),
            'payment_date' => now(),
        ]);
    }

    public function startDeduction($firstDeductionDate = null)
    {
        $this->update([
            'status' => 'in_deduction',
            'first_deduction_date' => $firstDeductionDate ?? now(),
        ]);
    }

    public function recordInstallmentPayment($amount)
    {
        $newBalance = $this->balance - $amount;
        $newInstallmentsPaid = $this->installments_paid + 1;
        
        $this->update([
            'balance' => max(0, $newBalance),
            'installments_paid' => $newInstallmentsPaid,
            'status' => $newBalance <= 0 ? 'completed' : 'in_deduction',
        ]);
    }
}
