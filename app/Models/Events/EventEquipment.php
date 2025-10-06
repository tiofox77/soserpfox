<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

class EventEquipment extends Model
{
    protected $table = 'events_event_equipment';

    protected $fillable = [
        'event_id', 'equipment_id', 'quantity', 'unit_price',
        'total_price', 'days', 'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function event() { return $this->belongsTo(Event::class); }
    public function equipment() { return $this->belongsTo(Equipment::class); }
}
