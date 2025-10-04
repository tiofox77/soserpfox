<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class Transaction extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_transactions';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'account_id',
        'cash_register_id',
        'payment_method_id',
        'invoice_id',
        'purchase_id',
        'transaction_number',
        'type',
        'category',
        'amount',
        'currency',
        'transaction_date',
        'reference',
        'description',
        'notes',
        'status',
        'is_reconciled',
        'reconciled_at',
        'attachment',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }
    
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
    
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\Invoice::class);
    }
    
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\Purchase::class);
    }
}
