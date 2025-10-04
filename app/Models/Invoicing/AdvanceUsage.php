<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceUsage extends Model
{
    protected $table = 'invoicing_advance_usages';

    protected $fillable = [
        'advance_id',
        'invoice_id',
        'amount_used',
        'used_date',
    ];

    protected $casts = [
        'amount_used' => 'decimal:2',
        'used_date' => 'datetime',
    ];

    public $timestamps = true;

    // Relacionamentos
    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }
}
