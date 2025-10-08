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
        'capacity' => 'integer',
    ];

    // Relationships
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    // Attributes
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
        ]);
        
        return implode(', ', $parts);
    }

    // Methods
    public function getEventsCount()
    {
        return $this->events()->count();
    }

    public function getUpcomingEventsCount()
    {
        return $this->events()
            ->where('start_date', '>', now())
            ->count();
    }

    public function canBeDeleted()
    {
        return $this->events()->count() === 0;
    }

    public function getOccupancyRate()
    {
        if (!$this->capacity) {
            return 0;
        }

        $totalAttendees = $this->events()
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->sum('expected_attendees');

        return min(100, ($totalAttendees / $this->capacity) * 100);
    }
}
