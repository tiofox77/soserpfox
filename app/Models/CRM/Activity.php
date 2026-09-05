<?php

namespace App\Models\CRM;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma interacção ou tarefa: a chamada feita, a reunião marcada, o que ficou
 * de se fazer. É o que separa um CRM de uma lista de nomes — a resposta a
 * «quando foi a última vez que falámos com este cliente?».
 */
class Activity extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_activities';

    protected $fillable = [
        'tenant_id', 'type', 'direction', 'subject', 'notes', 'lead_id', 'opportunity_id',
        'due_at', 'done', 'assigned_to', 'created_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'done' => 'boolean',
    ];

    public const TIPOS = [
        'chamada' => 'Chamada',
        'reuniao' => 'Reunião',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'visita' => 'Visita',
        'tarefa' => 'Tarefa',
        'nota' => 'Nota',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TIPOS[$this->type] ?? $this->type;
    }

    /** Uma tarefa com prazo estourado e por fazer. */
    public function atrasada(): bool
    {
        return ! $this->done && $this->due_at && $this->due_at->isPast();
    }
}
