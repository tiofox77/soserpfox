<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportGuideItem extends Model
{
    protected $table = 'invoicing_transport_guide_items';

    protected $fillable = [
        'transport_guide_id', 'product_id', 'product_name', 'description', 'quantity', 'unit',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function guide(): BelongsTo
    {
        return $this->belongsTo(TransportGuide::class, 'transport_guide_id');
    }
}
