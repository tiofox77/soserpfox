<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_venues';

    protected $fillable = ['tenant_id', 'code', 'name', 'warehouse_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
