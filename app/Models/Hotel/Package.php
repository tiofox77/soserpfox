<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_packages';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'type',
        'price',
        'discount_percentage',
        'discount_amount',
        'min_nights',
        'max_nights',
        'valid_from',
        'valid_until',
        'included_services',
        'room_type_ids',
        'image',
        'is_active',
        'show_online',
        'priority',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'min_nights' => 'integer',
        'max_nights' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'included_services' => 'array',
        'room_type_ids' => 'array',
        'is_active' => 'boolean',
        'show_online' => 'boolean',
        'priority' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($package) {
            if (empty($package->slug)) {
                $package->slug = Str::slug($package->name);
            }
        });
    }

    public function roomTypes()
    {
        return RoomType::whereIn('id', $this->room_type_ids ?? []);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        
        $now = now()->startOfDay();
        
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;
        
        return true;
    }

    public function calculateDiscount($basePrice, $nights = 1): float
    {
        if ($this->price) {
            // Preço fixo do pacote
            return max(0, ($basePrice * $nights) - $this->price);
        }
        
        if ($this->discount_percentage) {
            return ($basePrice * $nights) * ($this->discount_percentage / 100);
        }
        
        if ($this->discount_amount) {
            return $this->discount_amount;
        }
        
        return 0;
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'romantic' => 'Romântico',
            'family' => 'Família',
            'business' => 'Negócios',
            'wellness' => 'Bem-estar',
            'adventure' => 'Aventura',
            default => 'Outro',
        };
    }

    public function getTypeIcon(): string
    {
        return match($this->type) {
            'romantic' => 'fa-heart',
            'family' => 'fa-users',
            'business' => 'fa-briefcase',
            'wellness' => 'fa-spa',
            'adventure' => 'fa-mountain',
            default => 'fa-gift',
        };
    }

    public function getTypeColor(): string
    {
        return match($this->type) {
            'romantic' => 'pink',
            'family' => 'blue',
            'business' => 'gray',
            'wellness' => 'green',
            'adventure' => 'orange',
            default => 'purple',
        };
    }
}
