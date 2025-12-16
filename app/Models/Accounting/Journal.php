<?php

namespace App\Models\Accounting;

use App\Models\Tenant;
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
    
    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
    
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
    
    public function scopeOrdered($query)
    {
        return $query->orderBy('code');
    }
    
    // Relações
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
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
    
    public function documentTypes(): HasMany
    {
        return $this->hasMany(DocumentType::class);
    }
}
