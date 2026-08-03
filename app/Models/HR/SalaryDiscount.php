<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\BelongsToTenant;

class SalaryDiscount extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'hr_salary_discounts';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'discount_type',
        'request_date',
        'amount',
        'installments',
        'installment_amount',
        'remaining_installments',
        'reason',
        'status',
        'signed_document',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'installments' => 'integer',
        'remaining_installments' => 'integer',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function scopeActive($query)
    {
        return $query->where('status', 'approved')->where('remaining_installments', '>', 0);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pendente</span>',
            'approved' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aprovado</span>',
            'rejected' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Rejeitado</span>',
            'completed' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Concluído</span>',
            default => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Indefinido</span>',
        };
    }

    public function getDiscountTypeNameAttribute()
    {
        return match($this->discount_type) {
            'damages' => 'Danos/Avarias',
            'loan' => 'Empréstimo',
            'union_fee' => 'Quota Sindical',
            'disciplinary' => 'Disciplinar',
            'other' => 'Outro',
            default => $this->discount_type ?? 'Não definido',
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

    public function registerPayment(): void
    {
        $this->remaining_installments--;
        if ($this->remaining_installments <= 0) {
            $this->remaining_installments = 0;
            $this->status = 'completed';
        }
        $this->save();
    }

    public function calculateInstallmentAmount(): float
    {
        if ($this->installments <= 0) return $this->amount;
        return round($this->amount / $this->installments, 2);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($discount) {
            $discount->installment_amount = $discount->calculateInstallmentAmount();
            $discount->remaining_installments = $discount->installments;
        });
    }
}
