<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Move extends Model
{
    protected $table = 'accounting_moves';
    
    protected $fillable = [
        'tenant_id',
        'journal_id',
        'period_id',
        'date',
        'ref',
        'narration',
        'state',
        'total_debit',
        'total_credit',
        'created_by',
        'posted_by',
        'posted_at',
    ];
    
    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];
    
    // Relações
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
    
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
    
    public function lines(): HasMany
    {
        return $this->hasMany(MoveLine::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
