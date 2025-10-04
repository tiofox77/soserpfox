<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class PaymentMethod extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_payment_methods';
    
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'description',
        'icon',
        'color',
        'fee_percentage',
        'fee_fixed',
        'requires_account',
        'is_active',
        'sort_order',
    ];
    
    protected $casts = [
        'fee_percentage' => 'decimal:2',
        'fee_fixed' => 'decimal:2',
        'requires_account' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
