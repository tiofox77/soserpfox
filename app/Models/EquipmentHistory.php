<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'equipment_history';

    protected $fillable = [
        'equipment_id',
        'tenant_id',
        'action_type',
        'event_id',
        'client_id',
        'user_id',
        'start_datetime',
        'end_datetime',
        'hours_used',
        'location_from',
        'location_to',
        'notes',
        'status_before',
        'status_after',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'hours_used' => 'integer',
    ];

    // Relacionamentos
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Events\Event::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getActionTypeLabelAttribute(): string
    {
        return match($this->action_type) {
            'uso' => 'Uso em Evento',
            'reserva' => 'Reserva',
            'emprestimo' => 'Empréstimo',
            'devolucao' => 'Devolução',
            'manutencao' => 'Manutenção',
            'avaria' => 'Avaria Reportada',
            'reparacao' => 'Reparação',
            'transferencia' => 'Transferência',
            default => 'Outra Ação',
        };
    }

    // Métodos estáticos
    public static function calculateHours($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }
        
        return ceil($start->diffInHours($end));
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($history) {
            if ($history->start_datetime && $history->end_datetime && !$history->hours_used) {
                $history->hours_used = self::calculateHours(
                    $history->start_datetime,
                    $history->end_datetime
                );
            }
        });
    }
}
