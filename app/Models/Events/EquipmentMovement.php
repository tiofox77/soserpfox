<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMovement extends Model
{
    protected $table = 'events_equipment_movements';
    
    protected $fillable = [
        'event_id',
        'equipment_id',
        'type',
        'quantity',
        'technician_id',
        'team_id',
        'movement_datetime',
        'condition',
        'observations',
        'location_from',
        'location_to',
        'registered_by',
    ];
    
    protected $casts = [
        'movement_datetime' => 'datetime',
        'quantity' => 'integer',
    ];
    
    // Relacionamentos
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
    
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Equipment::class);
    }
    
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
    
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'registered_by');
    }
    
    // Scopes
    public function scopeSaida($query)
    {
        return $query->where('type', 'saida');
    }
    
    public function scopeRetorno($query)
    {
        return $query->where('type', 'retorno');
    }
    
    public function scopeDamaged($query)
    {
        return $query->whereIn('condition', ['danificado', 'quebrado']);
    }
}
