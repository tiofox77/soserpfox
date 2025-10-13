<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    protected $fillable = [
        'tenant_id',
        'account_id',
        'statement_date',
        'statement_balance',
        'book_balance',
        'difference',
        'status',
        'notes',
        'file_path',
        'file_type',
        'reconciled_by',
        'reconciled_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function items()
    {
        return $this->hasMany(BankReconciliationItem::class, 'reconciliation_id');
    }

    public function reconciledBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'reconciled_by');
    }
}
