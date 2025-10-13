<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Leave extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_leaves';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'leave_number',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'working_days',
        'reason',
        'notes',
        'has_medical_certificate',
        'document_path',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'paid',
        'deduction_amount',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'has_medical_certificate' => 'boolean',
        'paid' => 'boolean',
        'total_days' => 'integer',
        'working_days' => 'integer',
        'deduction_amount' => 'decimal:2',
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

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('leave_type', $type);
    }

    // Accessors
    public function getLeaveTypeNameAttribute()
    {
        return match($this->leave_type) {
            'sick' => 'Doença',
            'maternity' => 'Maternidade',
            'paternity' => 'Paternidade',
            'bereavement' => 'Luto',
            'marriage' => 'Casamento',
            'study' => 'Estudos',
            'unpaid' => 'Sem Vencimento',
            'justified' => 'Falta Justificada',
            'other' => 'Outro',
            default => 'Indefinido',
        };
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="badge bg-warning">Pendente</span>',
            'approved' => '<span class="badge bg-success">Aprovada</span>',
            'rejected' => '<span class="badge bg-danger">Rejeitada</span>',
            'cancelled' => '<span class="badge bg-secondary">Cancelada</span>',
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

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}
