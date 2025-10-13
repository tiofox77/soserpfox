<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationMatrix extends Model
{
    protected $fillable = [
        'tenant_id',
        'account_code',
        'function_type',
        'allocation_percent',
        'notes',
    ];
    
    protected $casts = [
        'allocation_percent' => 'decimal:2',
    ];
    
    // Relações
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_code', 'code');
    }
    
    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
    
    public function scopeForAccount($query, $accountCode)
    {
        return $query->where('account_code', $accountCode);
    }
}
