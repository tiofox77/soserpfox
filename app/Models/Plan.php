<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'max_users',
        'max_companies',
        'max_storage_mb',
        'features',
        'included_modules',
        'is_active',
        'is_featured',
        'trial_days',
        'order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'included_modules' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    // Relacionamentos
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'plan_module')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Métodos auxiliares
    public function getPrice($billingCycle = 'monthly')
    {
        return $billingCycle === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }

    public function hasModule($moduleSlug)
    {
        return in_array($moduleSlug, $this->included_modules ?? []);
    }

    public function getYearlySavings()
    {
        $monthlyYearly = $this->price_monthly * 12;
        return $monthlyYearly - $this->price_yearly;
    }

    public function getYearlySavingsPercentage()
    {
        $monthlyYearly = $this->price_monthly * 12;
        if ($monthlyYearly == 0) return 0;
        
        return round((($monthlyYearly - $this->price_yearly) / $monthlyYearly) * 100);
    }
}
