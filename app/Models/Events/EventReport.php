<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReport extends Model
{
    protected $table = 'events_reports';
    
    protected $fillable = [
        'event_id',
        'report_number',
        'type',
        'report_date',
        'event_start',
        'event_end',
        'team_id',
        'technicians',
        'summary',
        'equipments_used',
        'incidents',
        'observations',
        'setup_duration',
        'teardown_duration',
        'client_satisfaction',
        'client_feedback',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];
    
    protected $casts = [
        'report_date' => 'datetime',
        'event_start' => 'datetime',
        'event_end' => 'datetime',
        'technicians' => 'array',
        'equipments_used' => 'array',
        'incidents' => 'array',
        'approved_at' => 'datetime',
    ];
    
    // Relacionamentos
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
    
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
    
    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
    
    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalizado');
    }
    
    public function scopeApproved($query)
    {
        return $query->where('status', 'aprovado');
    }
    
    // Métodos
    public function approve($userId)
    {
        $this->update([
            'status' => 'aprovado',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }
}
