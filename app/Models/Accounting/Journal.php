<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    protected $table = 'accounting_journals';
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'sequence_prefix',
        'last_number',
        'default_debit_account_id',
        'default_credit_account_id',
        'active',
    ];
    
    protected $casts = [
        'active' => 'boolean',
        'last_number' => 'integer',
    ];
    
    // Relações
    public function moves(): HasMany
    {
        return $this->hasMany(Move::class);
    }
    
    public function defaultDebitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_debit_account_id');
    }
    
    public function defaultCreditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_credit_account_id');
    }
}
