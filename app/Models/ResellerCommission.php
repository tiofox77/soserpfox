<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UMA COMISSÃO — a de um pagamento confirmado de uma empresa ligada (RV-11).
 *
 * Nunca se apaga: anula-se com motivo. A `rule` é a regra tal como estava.
 */
class ResellerCommission extends Model
{
    public const ESTADOS = [
        'por_pagar' => 'Por pagar',
        'paga' => 'Paga',
        'anulada' => 'Anulada',
        // O revendedor pagou pelo cliente ao preço de revendedor: a comissão
        // ficou-lhe à cabeça, no desconto. Não entra no «por pagar».
        'compensada' => 'Descontada no pagamento',
    ];

    public const ORIGENS = [
        'order' => 'Pedido de subscrição aprovado',
        'invoice' => 'Factura da subscrição paga',
    ];

    protected $fillable = [
        'reseller_id', 'tenant_id', 'origin_type', 'origin_id', 'plan_id',
        'base_amount', 'amount', 'rule', 'status',
    ];

    protected $casts = [
        'rule' => 'array',
        'base_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function revendedor(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(ResellerPayout::class, 'payout_id');
    }
}
