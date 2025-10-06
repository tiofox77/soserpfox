<?php

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $table = 'events_checklists';

    protected $fillable = [
        'event_id', 'task', 'description', 'status', 'assigned_to',
        'due_date', 'completed_at', 'order',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function event() { return $this->belongsTo(Event::class); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_to'); }
}
