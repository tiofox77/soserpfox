<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma forma de pagamento de uma venda POS (multi-tender). */
class SalePayment extends Model
{
    protected $table = 'invoicing_sale_payments';

    protected $fillable = [
        'sales_invoice_id', 'tenant_id', 'payment_method',
        'payment_method_id', 'amount', 'reference', 'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }
}
