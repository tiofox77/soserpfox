<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\HR\Employee;

class WorkOrderItem extends Model
{
    use HasFactory;

    protected $table = 'workshop_work_order_items';

    protected $fillable = [
        'work_order_id',
        'service_id',
        'type',
        'code',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'hours',
        'mechanic_id',
        'part_number',
        'brand',
        'is_original',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hours' => 'decimal:2',
        'is_original' => 'boolean',
    ];

    protected $appends = ['formatted_subtotal'];

    // Relationships
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function mechanic()
    {
        return $this->belongsTo(Employee::class, 'mechanic_id');
    }

    // Accessors
    public function getFormattedSubtotalAttribute()
    {
        return number_format($this->subtotal, 2, ',', '.') . ' Kz';
    }

    // Methods
    public function isService()
    {
        return $this->type === 'service';
    }

    public function isPart()
    {
        return $this->type === 'part';
    }

    public function calculateSubtotal()
    {
        $baseAmount = $this->quantity * $this->unit_price;
        
        if ($this->discount_percent > 0) {
            $this->discount_amount = $baseAmount * ($this->discount_percent / 100);
        }
        
        $this->subtotal = $baseAmount - $this->discount_amount;
        $this->save();
        
        // Recalcular totais da OS
        $this->workOrder->calculateTotals();
    }

    protected static function booted()
    {
        static::created(function ($item) {
            $item->calculateSubtotal();
        });

        static::updated(function ($item) {
            $item->calculateSubtotal();
        });

        static::deleted(function ($item) {
            $item->workOrder->calculateTotals();
        });
    }
}
