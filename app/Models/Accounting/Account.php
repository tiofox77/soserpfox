<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'accounting_accounts';
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'nature',
        'parent_id',
        'level',
        'is_view',
        'blocked',
        'integration_key',
        'description',
        // Novos campos do Excel
        'default_tax_id',
        'debit_reflection_account_id',
        'credit_reflection_account_id',
        'default_cost_center_id',
        'account_key',
        'is_fixed_cost',
        'account_subtype',
    ];
    
    protected $casts = [
        'is_view' => 'boolean',
        'blocked' => 'boolean',
        'is_fixed_cost' => 'boolean',
    ];
    
    // Relações
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }
    
    public function moveLines(): HasMany
    {
        return $this->hasMany(MoveLine::class);
    }
    
    // Novos relacionamentos
    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }
    
    public function debitReflectionAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_reflection_account_id');
    }
    
    public function creditReflectionAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_reflection_account_id');
    }
    
    public function defaultCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'default_cost_center_id');
    }
}
