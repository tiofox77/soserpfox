<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class Reconciliation extends Model
{
    use BelongsToTenant;
    
    protected $table = 'treasury_reconciliations';
    
    protected $fillable = [
        'tenant_id',
        'account_id',
        'user_id',
        'reconciliation_number',
        'reconciliation_date',
        'statement_start_date',
        'statement_end_date',
        'statement_balance',
        'system_balance',
        'difference',
        'total_transactions',
        'reconciled_transactions',
        'pending_transactions',
        'status',
        'notes',
        'statement_file',
        'completed_at',
    ];
    
    protected $casts = [
        'reconciliation_date' => 'date',
        'statement_start_date' => 'date',
        'statement_end_date' => 'date',
        'statement_balance' => 'decimal:2',
        'system_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'total_transactions' => 'integer',
        'reconciled_transactions' => 'integer',
        'pending_transactions' => 'integer',
        'completed_at' => 'datetime',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
