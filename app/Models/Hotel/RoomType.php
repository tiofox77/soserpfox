<?php

namespace App\Models\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_room_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'base_price',
        'weekend_price',
        'capacity',
        'extra_bed_capacity',
        'extra_bed_price',
        'amenities',
        'featured_image',
        'gallery',
        'images',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'weekend_price' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
        'amenities' => 'array',
        'gallery' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    // Accessor para URL da imagem de destaque
    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }
        return null;
    }

    // Accessor para URLs da galeria
    public function getGalleryUrlsAttribute()
    {
        if ($this->gallery && is_array($this->gallery)) {
            return array_map(fn($img) => asset('storage/' . $img), $this->gallery);
        }
        return [];
    }

    // Boot
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = activeTenantId();
            }
            if (!$model->code) {
                $model->code = strtoupper(substr($model->name, 0, 3)) . '-' . str_pad(static::where('tenant_id', $model->tenant_id)->count() + 1, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    // Relationships
    public function rooms()
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'room_type_id');
    }

    // Accessors
    public function getAvailableRoomsCountAttribute()
    {
        return $this->rooms()->where('status', 'available')->where('is_active', true)->count();
    }

    public function getTotalRoomsCountAttribute()
    {
        return $this->rooms()->where('is_active', true)->count();
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->base_price, 2, ',', '.') . ' Kz';
    }
}
