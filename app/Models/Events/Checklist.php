<?php

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $table = 'events_checklists';

    protected $fillable = [
        'event_id', 'task', 'description', 'phase', 'status', 'assigned_to',
        'due_date', 'completed_at', 'order', 'is_required',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'is_required' => 'boolean',
    ];

    public function event() { return $this->belongsTo(Event::class); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_to'); }
    
    /**
     * Marca tarefa como concluída
     */
    public function markAsCompleted()
    {
        $this->status = 'concluido';
        $this->completed_at = now();
        $this->save();
        
        // Atualiza progresso do evento
        $this->event->updateChecklistProgress();
    }
    
    /**
     * Scope para filtrar por fase
     */
    public function scopeForPhase($query, $phase)
    {
        return $query->where('phase', $phase);
    }
    
    /**
     * Scope para tarefas obrigatórias
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }
}
