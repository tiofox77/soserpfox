<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_kitchen_stations';
    protected $fillable = ['tenant_id', 'venue_id', 'code', 'name', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function tickets(): HasMany { return $this->hasMany(KitchenTicket::class, 'station_id'); }
}
