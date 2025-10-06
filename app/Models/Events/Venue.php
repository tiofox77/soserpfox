<?php

namespace App\Models\Events;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use BelongsToTenant;

    protected $table = 'events_venues';

    protected $fillable = [
        'tenant_id', 'name', 'address', 'city', 'phone',
        'contact_person', 'capacity', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function events() { return $this->hasMany(Event::class); }
}
