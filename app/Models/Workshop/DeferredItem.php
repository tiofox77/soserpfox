<?php

namespace App\Models\Workshop;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UMA RECOMENDAÇÃO ADIADA (OF-12): um trabalho que ficou por fazer numa viatura.
 */
class DeferredItem extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_deferred_items';

    public const ORIGENS = [
        'recusada' => 'Recusada pelo cliente',
        'adiada' => 'Adiada',
        'inspeccao' => 'Da inspecção',
    ];

    public const ESTADOS = [
        'pendente' => 'Por propor',
        'aceite' => 'Aceite',
        'descartada' => 'Descartada',
    ];

    protected $fillable = [
        'tenant_id', 'vehicle_id', 'work_order_id', 'work_order_item_id', 'type', 'service_id', 'product_id', 'code',
        'name', 'description', 'quantity', 'unit_price', 'hours', 'origin', 'severity', 'follow_up_on', 'status',
        'resolved_work_order_id', 'resolved_at', 'resolved_by', 'note', 'user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'hours' => 'decimal:2',
        'follow_up_on' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function resolvedWorkOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'resolved_work_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function valor(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
