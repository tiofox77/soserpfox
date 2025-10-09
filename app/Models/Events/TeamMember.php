<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $table = 'events_team_members';
    
    protected $fillable = [
        'team_id',
        'technician_id',
        'role',
    ];
    
    // Relacionamentos
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}
