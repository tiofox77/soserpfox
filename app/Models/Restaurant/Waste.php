<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waste extends Model
{
    use BelongsToTenant;
    protected $table = 'restaurant_wastes';
    protected $fillable = ['tenant_id', 'order_id', 'order_item_id', 'quantity', 'reason', 'user_id'];
    protected $casts = ['quantity' => 'decimal:4'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
}
