<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;

class CashRegister extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_cash_registers';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'code',
        'opening_balance',
        'current_balance',
        'expected_balance',
        'opened_at',
        'closed_at',
        'status',
        'opening_notes',
        'closing_notes',
        'is_active',
        'is_default',
    ];
    
    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'cash_register_id');
    }
}
