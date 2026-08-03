<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    use BelongsToTenant;
    protected $table = 'restaurant_payment_attempts';
    protected $fillable = ['tenant_id', 'order_id', 'idempotency_key', 'sales_invoice_id', 'document_type', 'amount', 'status', 'error'];
    protected $casts = ['amount' => 'decimal:2'];
}
