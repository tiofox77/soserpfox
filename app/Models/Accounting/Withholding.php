<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withholding extends Model
{
    protected $table = 'accounting_withholdings';
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'rate',
        'account_id',
        'active',
    ];
    
    protected $casts = [
        'rate' => 'decimal:2',
        'active' => 'boolean',
    ];
    
    // Relações
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
