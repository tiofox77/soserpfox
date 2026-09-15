<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UM PERÍODO DE TRABALHO DE UM MECÂNICO NUMA ORDEM (15/09/2026, OF-06).
 *
 * Aberto enquanto `ended_at` é nulo. Os minutos fecham-se ao parar — e voltam a
 * fazer-se sempre que alguém corrige as horas à mão.
 */
class TimeEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_time_entries';

    protected $fillable = ['tenant_id', 'work_order_id', 'work_order_item_id', 'mechanic_id', 'started_at', 'ended_at', 'minutes', 'notes', 'user_id'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime', 'minutes' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (TimeEntry $t) {
            $t->minutes = $t->ended_at ? max(0, (int) round($t->started_at->diffInSeconds($t->ended_at) / 60)) : 0;
        });
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(WorkOrderItem::class, 'work_order_item_id');
    }

    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Os minutos até agora — os que já fecharam, ou os que correm. */
    public function minutosAteAgora(): int
    {
        return $this->ended_at ? (int) $this->minutes : max(0, (int) floor($this->started_at->diffInSeconds(now()) / 60));
    }
}
