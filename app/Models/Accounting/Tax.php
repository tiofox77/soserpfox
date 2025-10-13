<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    protected $table = 'accounting_taxes';
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'rate',
        'account_collected_id',
        'account_paid_id',
        'valid_from',
        'valid_to',
        'active',
    ];
    
    protected $casts = [
        'rate' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'active' => 'boolean',
    ];
    
    // Relações
    public function accountCollected(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_collected_id');
    }
    
    public function accountPaid(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_paid_id');
    }
    
    public function moveLines(): HasMany
    {
        return $this->hasMany(MoveLine::class);
    }
}
