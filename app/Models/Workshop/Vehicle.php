<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_vehicles';

    protected $fillable = [
        'tenant_id',
        'plate',
        'vehicle_number',
        'owner_name',
        'owner_phone',
        'owner_email',
        'owner_nif',
        'owner_address',
        'brand',
        'model',
        'year',
        'color',
        'vin',
        'engine_number',
        'fuel_type',
        'mileage',
        'registration_document',
        'registration_expiry',
        'insurance_company',
        'insurance_policy',
        'insurance_expiry',
        'inspection_expiry',
        'status',
        'notes',
    ];

    protected $casts = [
        'registration_expiry' => 'date',
        'insurance_expiry' => 'date',
        'inspection_expiry' => 'date',
        'mileage' => 'integer',
        'year' => 'integer',
    ];

    protected $appends = ['full_name', 'is_document_expired'];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function activeWorkOrder()
    {
        return $this->hasOne(WorkOrder::class)
            ->whereIn('status', ['pending', 'scheduled', 'in_progress', 'waiting_parts'])
            ->latest();
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->brand} {$this->model} ({$this->plate})";
    }

    public function getIsDocumentExpiredAttribute()
    {
        $today = now();
        
        return ($this->registration_expiry && $this->registration_expiry->isPast()) ||
               ($this->insurance_expiry && $this->insurance_expiry->isPast()) ||
               ($this->inspection_expiry && $this->inspection_expiry->isPast());
    }

    public function getExpiringDocumentsAttribute()
    {
        $expiring = [];
        $today = now();
        $warningDays = 30;

        $documents = [
            'Livrete' => $this->registration_expiry,
            'Seguro' => $this->insurance_expiry,
            'Inspeção' => $this->inspection_expiry,
        ];

        foreach ($documents as $name => $date) {
            if ($date && $date->isFuture() && $date->diffInDays($today) <= $warningDays) {
                $expiring[] = [
                    'name' => $name,
                    'date' => $date,
                    'days' => $today->diffInDays($date),
                ];
            }
        }

        return $expiring;
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInService($query)
    {
        return $query->where('status', 'in_service');
    }

    // Methods
    public function isActive()
    {
        return $this->status === 'active';
    }

    public function getTotalWorkOrders()
    {
        return $this->workOrders()->count();
    }

    public function getTotalSpent()
    {
        return $this->workOrders()
            ->where('payment_status', 'paid')
            ->sum('total');
    }
}
