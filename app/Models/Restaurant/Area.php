<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_areas';

    protected $fillable = ['tenant_id', 'venue_id', 'name', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}

