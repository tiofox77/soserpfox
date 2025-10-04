<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class Transfer extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_transfers';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'from_account_id',
        'from_cash_register_id',
        'to_account_id',
        'to_cash_register_id',
        'transfer_number',
        'amount',
        'currency',
        'fee',
        'transfer_date',
        'description',
        'notes',
        'status',
        'reference',
        'attachment',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'transfer_date' => 'date',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }
    
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
    
    public function fromCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'from_cash_register_id');
    }
    
    public function toCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'to_cash_register_id');
    }
}
