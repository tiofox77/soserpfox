<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UMA MARCAÇÃO NA AGENDA DA OFICINA (15/09/2026, OF-05).
 *
 * `marcada` → `confirmada` → `chegou` (passou a ordem de serviço) — ou `faltou`
 * ou `cancelada`. Só as marcadas e confirmadas ocupam o lugar e o mecânico.
 */
class Appointment extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_appointments';

    protected $fillable = [
        'tenant_id', 'vehicle_id', 'plate', 'customer_name', 'customer_phone', 'bay_id', 'mechanic_id',
        'starts_at', 'ends_at', 'service', 'status', 'notes', 'work_order_id', 'user_id',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public const ESTADOS = [
        'marcada' => 'Marcada',
        'confirmada' => 'Confirmada',
        'chegou' => 'Chegou',
        'faltou' => 'Faltou',
        'cancelada' => 'Cancelada',
    ];

    /** Estas ocupam o elevador e o mecânico; as outras já não. */
    public const OCUPAM = ['marcada', 'confirmada'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function bay()
    {
        return $this->belongsTo(Bay::class);
    }

    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
