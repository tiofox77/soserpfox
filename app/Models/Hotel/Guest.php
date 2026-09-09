<?php

namespace App\Models\Hotel;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Guest extends Model
{
    use HasFactory, SoftDeletes, Notifiable, BelongsToTenant;

    /** Guest uses its own email field for mail notifications */
    public function routeNotificationForMail($notification = null)
    {
        return $this->email;
    }

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
        'loyalty_points',
        'loyalty_tier',
        'total_spent',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'preferences' => 'array',
        'is_vip' => 'boolean',
        'is_blacklisted' => 'boolean',
        'loyalty_points' => 'integer',
        'total_spent' => 'decimal:2',
    ];

    const TIERS = [
        'bronze' => 'Bronze',
        'silver' => 'Silver',
        'gold' => 'Gold',
        'platinum' => 'Platinum',
    ];

    const TIER_COLORS = [
        'bronze' => 'amber',
        'silver' => 'gray',
        'gold' => 'yellow',
        'platinum' => 'purple',
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

    public function getTierLabelAttribute()
    {
        return self::TIERS[$this->loyalty_tier] ?? 'Bronze';
    }

    public function getTierColorAttribute()
    {
        return self::TIER_COLORS[$this->loyalty_tier] ?? 'amber';
    }

    /**
     * Award loyalty points based on amount spent, recalculate tier and increase total_spent.
     */
    public function awardLoyalty(float $amount): void
    {
        $settings = HotelSettings::getForTenant($this->tenant_id);
        if (!($settings->loyalty_enabled ?? true)) {
            return;
        }

        $rate = (float) ($settings->loyalty_points_per_kz ?? 0.01);
        $pointsEarned = (int) floor($amount * $rate);

        $this->increment('total_spent', $amount);
        if ($pointsEarned > 0) {
            $this->increment('loyalty_points', $pointsEarned);
        }
        $this->refresh();
        $this->recalculateTier($settings);
    }

    public function recalculateTier(?HotelSettings $settings = null): string
    {
        $settings = $settings ?? HotelSettings::getForTenant($this->tenant_id);
        $points = $this->loyalty_points;

        $tier = 'bronze';
        if ($points >= ($settings->loyalty_tier_platinum ?? 5000)) {
            $tier = 'platinum';
        } elseif ($points >= ($settings->loyalty_tier_gold ?? 2000)) {
            $tier = 'gold';
        } elseif ($points >= ($settings->loyalty_tier_silver ?? 500)) {
            $tier = 'silver';
        }

        if ($tier !== $this->loyalty_tier) {
            $this->update(['loyalty_tier' => $tier]);
        }

        return $tier;
    }
}
