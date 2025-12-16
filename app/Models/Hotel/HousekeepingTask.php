<?php

namespace App\Models\Hotel;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class HousekeepingTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hotel_housekeeping_tasks';

    protected $fillable = [
        'tenant_id',
        'room_id',
        'assigned_to',
        'reservation_id',
        'task_type',
        'priority',
        'status',
        'scheduled_date',
        'scheduled_time',
        'estimated_duration',
        'started_at',
        'completed_at',
        'verified_at',
        'verified_by',
        'actual_duration',
        'checklist',
        'notes',
        'issues',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'checklist' => 'array',
    ];

    const TASK_TYPES = [
        'checkout_clean' => 'Limpeza Check-out',
        'stay_clean' => 'Limpeza Estadia',
        'deep_clean' => 'Limpeza Profunda',
        'turndown' => 'Turndown Service',
        'inspection' => 'Inspeção',
    ];

    const PRIORITIES = [
        'urgent' => 'Urgente',
        'high' => 'Alta',
        'normal' => 'Normal',
        'low' => 'Baixa',
    ];

    const PRIORITY_COLORS = [
        'urgent' => 'red',
        'high' => 'orange',
        'normal' => 'blue',
        'low' => 'gray',
    ];

    const STATUSES = [
        'pending' => 'Pendente',
        'in_progress' => 'Em Progresso',
        'completed' => 'Concluído',
        'verified' => 'Verificado',
        'issue' => 'Com Problema',
    ];

    const STATUS_COLORS = [
        'pending' => 'yellow',
        'in_progress' => 'blue',
        'completed' => 'green',
        'verified' => 'emerald',
        'issue' => 'red',
    ];

    // Checklist padrão por tipo de tarefa
    const DEFAULT_CHECKLISTS = [
        'checkout_clean' => [
            ['item' => 'Retirar roupa de cama', 'done' => false],
            ['item' => 'Trocar lençóis e fronhas', 'done' => false],
            ['item' => 'Limpar casa de banho', 'done' => false],
            ['item' => 'Repor amenities', 'done' => false],
            ['item' => 'Aspirar/varrer chão', 'done' => false],
            ['item' => 'Limpar espelhos e vidros', 'done' => false],
            ['item' => 'Verificar minibar', 'done' => false],
            ['item' => 'Limpar frigobar', 'done' => false],
            ['item' => 'Verificar ar condicionado', 'done' => false],
            ['item' => 'Verificar TV e controlo', 'done' => false],
            ['item' => 'Verificar fechadura', 'done' => false],
            ['item' => 'Repor material de escritório', 'done' => false],
        ],
        'stay_clean' => [
            ['item' => 'Fazer camas', 'done' => false],
            ['item' => 'Limpar casa de banho', 'done' => false],
            ['item' => 'Repor toalhas', 'done' => false],
            ['item' => 'Repor amenities', 'done' => false],
            ['item' => 'Esvaziar lixo', 'done' => false],
            ['item' => 'Aspirar/varrer', 'done' => false],
        ],
        'deep_clean' => [
            ['item' => 'Limpeza completa de check-out', 'done' => false],
            ['item' => 'Lavar cortinas', 'done' => false],
            ['item' => 'Limpar ar condicionado', 'done' => false],
            ['item' => 'Lavar colchões', 'done' => false],
            ['item' => 'Limpar carpetes/tapetes', 'done' => false],
            ['item' => 'Limpar paredes', 'done' => false],
            ['item' => 'Desinfetar superfícies', 'done' => false],
        ],
        'turndown' => [
            ['item' => 'Preparar cama para noite', 'done' => false],
            ['item' => 'Fechar cortinas', 'done' => false],
            ['item' => 'Colocar chocolates/águas', 'done' => false],
            ['item' => 'Ajustar iluminação', 'done' => false],
            ['item' => 'Limpar casa de banho rapidamente', 'done' => false],
        ],
        'inspection' => [
            ['item' => 'Verificar limpeza geral', 'done' => false],
            ['item' => 'Verificar funcionamento equipamentos', 'done' => false],
            ['item' => 'Verificar amenities completos', 'done' => false],
            ['item' => 'Verificar danos', 'done' => false],
        ],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = activeTenantId();
            }
            if (empty($model->checklist)) {
                $model->checklist = self::DEFAULT_CHECKLISTS[$model->task_type] ?? [];
            }
        });
    }

    // Accessors
    public function getTaskTypeLabelAttribute()
    {
        return self::TASK_TYPES[$this->task_type] ?? $this->task_type;
    }

    public function getPriorityLabelAttribute()
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getPriorityColorAttribute()
    {
        return self::PRIORITY_COLORS[$this->priority] ?? 'gray';
    }

    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getChecklistProgressAttribute()
    {
        if (empty($this->checklist)) return 0;
        
        $total = count($this->checklist);
        $done = collect($this->checklist)->where('done', true)->count();
        
        return $total > 0 ? round(($done / $total) * 100) : 0;
    }

    public function getIsOverdueAttribute()
    {
        if ($this->status === 'completed' || $this->status === 'verified') {
            return false;
        }
        
        return $this->scheduled_date->lt(today());
    }

    // Actions
    public function start()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        
        // Atualizar status do quarto (ambos os campos)
        $this->room->update([
            'housekeeping_status' => 'in_progress',
            'status' => 'cleaning', // Status principal = Limpeza
        ]);
    }

    public function complete()
    {
        $startedAt = $this->started_at ?? now();
        $duration = $startedAt->diffInMinutes(now());
        
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_duration' => $duration,
        ]);
        
        // Atualizar status do quarto - disponível após limpeza
        $this->room->update([
            'housekeeping_status' => 'clean',
            'status' => 'available',
        ]);
    }

    public function verify($userId)
    {
        $this->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);
        
        // Atualizar status do quarto - agora disponível
        $this->room->update([
            'housekeeping_status' => 'clean',
            'status' => 'available', // Quarto disponível após verificação
        ]);
    }

    public function reportIssue($issues)
    {
        $this->update([
            'status' => 'issue',
            'issues' => $issues,
        ]);
    }

    public function updateChecklist($index, $done)
    {
        $checklist = $this->checklist;
        if (isset($checklist[$index])) {
            $checklist[$index]['done'] = $done;
            $this->update(['checklist' => $checklist]);
        }
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', today());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'verified']);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    public function scopeOverdue($query)
    {
        return $query->where('scheduled_date', '<', today())
            ->whereNotIn('status', ['completed', 'verified']);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    // Relationships
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function inspection()
    {
        return $this->hasOne(RoomInspection::class, 'housekeeping_task_id');
    }
}
