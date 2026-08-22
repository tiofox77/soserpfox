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
        'related_type',
        'related_id',
        'transaction_number',
        'type',
        'transaction_type_id',
        'category',
        'transaction_category_id',
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

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }
    
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\SalesInvoice::class, 'invoice_id');
    }
    
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Invoicing\PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
