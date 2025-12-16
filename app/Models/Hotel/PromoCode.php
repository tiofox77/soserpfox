<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_promo_codes';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_amount',
        'max_discount',
        'usage_limit',
        'usage_per_customer',
        'times_used',
        'valid_from',
        'valid_until',
        'room_type_ids',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_per_customer' => 'integer',
        'times_used' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'room_type_ids' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($code) {
            if (empty($code->code)) {
                $code->code = strtoupper(Str::random(8));
            }
        });
    }

    public function usages()
    {
        return $this->hasMany(PromoCodeUsage::class, 'promo_code_id');
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        
        $now = now()->startOfDay();
        
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;
        
        if ($this->usage_limit && $this->times_used >= $this->usage_limit) return false;
        
        return true;
    }

    public function canBeUsedByGuest($guestId): bool
    {
        if (!$this->isValid()) return false;
        
        $guestUsage = $this->usages()->where('guest_id', $guestId)->count();
        
        return $guestUsage < $this->usage_per_customer;
    }

    public function calculateDiscount($amount): float
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return 0;
        }

        $discount = 0;

        if ($this->discount_type === 'percentage') {
            $discount = $amount * ($this->discount_value / 100);
        } else {
            $discount = $this->discount_value;
        }

        if ($this->max_discount && $discount > $this->max_discount) {
            $discount = $this->max_discount;
        }

        return min($discount, $amount);
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }

    public function getStatusLabel(): string
    {
        if (!$this->is_active) return 'Inativo';
        if (!$this->isValid()) return 'Expirado';
        if ($this->usage_limit && $this->times_used >= $this->usage_limit) return 'Esgotado';
        return 'Ativo';
    }

    public function getStatusColor(): string
    {
        $status = $this->getStatusLabel();
        return match($status) {
            'Ativo' => 'green',
            'Inativo' => 'gray',
            'Expirado' => 'red',
            'Esgotado' => 'amber',
            default => 'gray',
        };
    }
}
