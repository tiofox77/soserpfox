<?php

namespace App\Models\Invoicing;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma contagem física de stock: o que a prateleira diz, contra o sistema.
 *
 * Fechada, é um documento — quem contou, quando, e cada diferença. As
 * diferenças fecham-se por MOVIMENTOS de stock, nunca por números por cima.
 */
class StockCount extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_stock_counts';

    protected $fillable = [
        'tenant_id', 'warehouse_id', 'status', 'notes',
        'opened_by', 'closed_by', 'closed_at',
        'items_counted', 'items_adjusted', 'adjustment_cost',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'adjustment_cost' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'stock_count_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function aberta(): bool
    {
        return $this->status === 'open';
    }
}
