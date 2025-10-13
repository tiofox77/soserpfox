<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant;
use App\Models\HR\Employee;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_work_orders';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'vehicle_id',
        'mechanic_id',
        'received_at',
        'scheduled_for',
        'started_at',
        'completed_at',
        'delivered_at',
        'mileage_in',
        'problem_description',
        'diagnosis',
        'work_performed',
        'recommendations',
        'status',
        'priority',
        'labor_total',
        'parts_total',
        'discount',
        'tax',
        'total',
        'payment_status',
        'paid_amount',
        'warranty_days',
        'warranty_expires',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'warranty_expires' => 'date',
        'mileage_in' => 'integer',
        'labor_total' => 'decimal:2',
        'parts_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'warranty_days' => 'integer',
    ];

    protected $appends = [
        'formatted_total',
        'days_in_service',
        'is_overdue',
        'balance_due'
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function mechanic()
    {
        return $this->belongsTo(Employee::class, 'mechanic_id');
    }

    public function items()
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function services()
    {
        return $this->items()->where('type', 'service');
    }

    public function parts()
    {
        return $this->items()->where('type', 'part');
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total, 2, ',', '.') . ' Kz';
    }

    public function getDaysInServiceAttribute()
    {
        if (!$this->received_at) return 0;
        
        $endDate = $this->delivered_at ?? now();
        return $this->received_at->diffInDays($endDate);
    }

    public function getIsOverdueAttribute()
    {
        if (!$this->scheduled_for) return false;
        
        return $this->scheduled_for->isPast() && 
               !in_array($this->status, ['completed', 'delivered', 'cancelled']);
    }

    public function getBalanceDueAttribute()
    {
        return $this->total - $this->paid_amount;
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    // Methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    public function calculateTotals()
    {
        $this->labor_total = $this->services()->sum('subtotal');
        $this->parts_total = $this->parts()->sum('subtotal');
        
        $subtotal = $this->labor_total + $this->parts_total;
        $afterDiscount = $subtotal - $this->discount;
        
        $this->total = $afterDiscount + $this->tax;
        $this->save();
    }

    public function markAsInProgress()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'warranty_expires' => now()->addDays($this->warranty_days),
        ]);
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function addPayment($amount)
    {
        $this->paid_amount += $amount;
        
        if ($this->paid_amount >= $this->total) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        }
        
        $this->save();
    }
}
