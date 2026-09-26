<?php

namespace App\Models\Invoicing;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosShiftTransaction extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_pos_shift_transactions';

    protected $fillable = [
        'shift_id',
        'tenant_id',
        'type',
        'reference_type',
        'reference_id',
        'reference_number',
        'payment_method',
        'amount',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relacionamentos
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class);
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'invoice' => __('Fatura'),
            'receipt' => __('Recibo'),
            // Vale NEGATIVO no turno: é dinheiro a sair da gaveta.
            'credit_note' => __('Nota de crédito'),
            'adjustment' => __('Ajuste'),
            'withdrawal' => __('Retirada'),
            'deposit' => __('Depósito'),
            default => $this->type,
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => __('Dinheiro'),
            'card' => __('Cartão'),
            'bank_transfer' => __('Transferência'),
            'mbway' => __('MB WAY'),
            'multibanco' => __('Multibanco'),
            'a_prazo' => __('A prazo'),
            'compra' => __('Compra'),
            default => $this->payment_method,
        };
    }
}
