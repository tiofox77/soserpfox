<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_guests';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'document_type',
        'document_number',
        'nationality',
        'birth_date',
        'gender',
        'address',
        'city',
        'country',
        'company',
        'nif',
        'notes',
        'preferences',
        'total_stays',
        'is_vip',
        'is_blacklisted',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'preferences' => 'array',
        'is_vip' => 'boolean',
        'is_blacklisted' => 'boolean',
    ];

    const DOCUMENT_TYPES = [
        'bi' => 'Bilhete de Identidade',
        'passport' => 'Passaporte',
        'driving_license' => 'Carta de Condução',
        'other' => 'Outro',
    ];

    // Boot
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = activeTenantId();
            }
        });
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeVip($query)
    {
        return $query->where('is_vip', true);
    }

    public function scopeNotBlacklisted($query)
    {
        return $query->where('is_blacklisted', false);
    }

    // Relationships
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'guest_id');
    }

    // Accessors
    public function getDocumentTypeLabelAttribute()
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }

    public function getAgeAttribute()
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getLastStayAttribute()
    {
        return $this->reservations()
            ->whereIn('status', ['checked_out', 'checked_in'])
            ->latest('check_in_date')
            ->first();
    }

    // Methods
    public function incrementStays()
    {
        $this->increment('total_stays');
    }
}
