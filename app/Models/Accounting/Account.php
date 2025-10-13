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
    ];
    
    protected $casts = [
        'is_view' => 'boolean',
        'blocked' => 'boolean',
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
}
