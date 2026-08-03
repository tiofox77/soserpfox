<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceUsage extends Model
{
    protected $table = 'invoicing_advance_usages';

    // Alinhado com o schema real (a coluna chama-se usage_date, não used_date —
    // o valor antigo rebentava o INSERT e abortava o pagamento inteiro).
    protected $fillable = [
        'advance_id',
        'invoice_id',
        'invoice_type',
        'amount_used',
        'usage_date',
        'notes',
    ];

    protected $casts = [
        'amount_used' => 'decimal:2',
        'usage_date' => 'date',
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
