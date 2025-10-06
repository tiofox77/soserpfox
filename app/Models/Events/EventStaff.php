<?php

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EventStaff extends Model
{
    protected $table = 'events_event_staff';

    protected $fillable = [
        'event_id', 'user_id', 'role', 'assigned_start',
        'assigned_end', 'cost',
    ];

    protected $casts = [
        'assigned_start' => 'datetime',
        'assigned_end' => 'datetime',
        'cost' => 'decimal:2',
    ];

    public function event() { return $this->belongsTo(Event::class); }
    public function user() { return $this->belongsTo(User::class); }
}
