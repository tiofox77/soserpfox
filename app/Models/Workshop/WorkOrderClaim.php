<?php

namespace App\Models\Workshop;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * O SINISTRO DE UMA ORDEM (OF-15): a seguradora, o processo, o perito e a franquia.
 */
class WorkOrderClaim extends Model
{
    use BelongsToTenant;

    protected $table = 'workshop_claims';

    public const ESTADOS = [
        'aberto' => 'Participado',
        'peritagem' => 'Em peritagem',
        'aprovado' => 'Aprovado pela seguradora',
        'recusado' => 'Recusado pela seguradora',
        'pago' => 'Pago pela seguradora',
    ];

    protected $fillable = [
        'tenant_id', 'work_order_id', 'insurer_client_id', 'claim_number', 'policy_number', 'accident_date',
        'adjuster_name', 'adjuster_phone', 'adjuster_email', 'inspection_date', 'approved_amount', 'excess_amount',
        'status', 'notes', 'excess_invoice_id', 'user_id',
    ];

    protected $casts = [
        'accident_date' => 'date',
        'inspection_date' => 'date',
        'approved_amount' => 'decimal:2',
        'excess_amount' => 'decimal:2',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function insurer()
    {
        return $this->belongsTo(Client::class, 'insurer_client_id');
    }

    public function excessInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'excess_invoice_id');
    }
}
