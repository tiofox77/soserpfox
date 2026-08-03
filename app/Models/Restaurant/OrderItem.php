<?php

namespace App\Models\Restaurant;

use App\Models\Product;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_order_items';

    protected $fillable = [
        'tenant_id', 'order_id', 'product_id', 'product_name', 'quantity',
        'unit', 'unit_price', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_amount', 'line_total', 'billed_quantity',
        'kitchen_status', 'notes', 'created_by',
        'stock_consumed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'billed_quantity' => 'decimal:4',
        'stock_consumed_at' => 'datetime',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
