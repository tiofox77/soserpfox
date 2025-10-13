<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveLine extends Model
{
    protected $table = 'accounting_move_lines';
    
    protected $fillable = [
        'tenant_id',
        'move_id',
        'account_id',
        'name',
        'partner_id',
        'debit',
        'credit',
        'balance',
        'tax_id',
        'tax_amount',
        'document_ref',
        'narration',
    ];
    
    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];
    
    // Relações
    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
