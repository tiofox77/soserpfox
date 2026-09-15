<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * UMA AVALIAÇÃO DO CLIENTE (OF-14): a nota, se recomendaria e o comentário.
 */
class WorkOrderSurvey extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_surveys';

    protected $fillable = [
        'tenant_id', 'work_order_id', 'vehicle_id', 'mechanic_id', 'token', 'score', 'would_recommend', 'comment', 'answered_at', 'sent_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'would_recommend' => 'boolean',
        'answered_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class);
    }
}
