<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    use BelongsToTenant;

    public const STATUSES = [
        'available' => 'Livre',
        'reserved' => 'Reservada',
        'occupied' => 'Ocupada',
        'waiting_kitchen' => 'Na cozinha',
        'served' => 'Servida',
        'billing' => 'A pedir conta',
        'cleaning' => 'Em limpeza',
        'blocked' => 'Bloqueada',
    ];

    protected $table = 'restaurant_tables';

    protected $fillable = [
        'tenant_id', 'venue_id', 'area_id', 'code', 'name', 'capacity',
        'status', 'position_x', 'position_y', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereNotIn('status', ['billed', 'cancelled'])
            ->latestOfMany();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

