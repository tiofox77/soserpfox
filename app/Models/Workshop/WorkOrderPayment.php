<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class WorkOrderPayment extends Model
{
    protected $table = 'workshop_work_order_payments';

    protected $fillable = [
        'work_order_id',
        'user_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // Métodos de pagamento
    const METHOD_CASH = 'cash';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_CARD = 'card';
    const METHOD_CHECK = 'check';
    const METHOD_OTHER = 'other';

    // Relationships
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', '.') . ' Kz';
    }

    public function getPaymentMethodLabelAttribute()
    {
        return match($this->payment_method) {
            self::METHOD_CASH => 'Dinheiro',
            self::METHOD_TRANSFER => 'Transferência',
            self::METHOD_CARD => 'Cartão',
            self::METHOD_CHECK => 'Cheque',
            self::METHOD_OTHER => 'Outro',
            default => 'Desconhecido',
        };
    }

    public function getPaymentMethodIconAttribute()
    {
        return match($this->payment_method) {
            self::METHOD_CASH => 'money-bill-wave',
            self::METHOD_TRANSFER => 'exchange-alt',
            self::METHOD_CARD => 'credit-card',
            self::METHOD_CHECK => 'file-invoice-dollar',
            self::METHOD_OTHER => 'question-circle',
            default => 'money-bill',
        };
    }
}
