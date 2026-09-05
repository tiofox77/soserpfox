<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha da contagem: o esperado (congelado à abertura) e o contado.
 */
class StockCountItem extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_stock_count_items';

    protected $fillable = [
        'tenant_id', 'stock_count_id', 'product_id',
        'expected_quantity', 'counted_quantity',
        'unit_cost', 'adjustment_movement_id', 'counted_by',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:4',
        'counted_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    /** A diferença: positiva = sobra na prateleira, negativa = falta. */
    public function diferenca(): ?float
    {
        if ($this->counted_quantity === null) {
            return null;
        }

        return round((float) $this->counted_quantity - (float) $this->expected_quantity, 4);
    }
}
