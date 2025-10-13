<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_services';

    protected $fillable = [
        'tenant_id',
        'service_code',
        'name',
        'description',
        'category',
        'labor_cost',
        'estimated_hours',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'labor_cost' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['formatted_labor_cost'];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workOrderItems()
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    // Accessors
    public function getFormattedLaborCostAttribute()
    {
        return number_format($this->labor_cost, 2, ',', '.') . ' Kz';
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Methods
    public function isActive()
    {
        return $this->is_active === true;
    }

    public function getTotalRevenue()
    {
        return $this->workOrderItems()
            ->whereHas('workOrder', function($q) {
                $q->where('payment_status', 'paid');
            })
            ->sum('subtotal');
    }

    public function getTimesUsed()
    {
        return $this->workOrderItems()->count();
    }
}
