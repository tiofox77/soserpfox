<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class BankReconciliationItem extends Model
{
    protected $fillable = [
        'reconciliation_id',
        'transaction_date',
        'reference',
        'description',
        'amount',
        'type',
        'status',
        'move_line_id',
        'match_confidence',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'match_confidence' => 'decimal:2',
    ];

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function moveLine()
    {
        return $this->belongsTo(MoveLine::class);
    }
}
