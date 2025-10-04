<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;

class Account extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_accounts';
    
    protected $fillable = [
        'tenant_id',
        'bank_id',
        'account_name',
        'account_number',
        'iban',
        'currency',
        'account_type',
        'initial_balance',
        'current_balance',
        'manager_name',
        'manager_phone',
        'manager_email',
        'notes',
        'is_active',
        'is_default',
        'show_on_invoice',
        'invoice_display_order',
    ];
    
    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'show_on_invoice' => 'boolean',
        'invoice_display_order' => 'integer',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }
    
    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class, 'account_id');
    }
}
