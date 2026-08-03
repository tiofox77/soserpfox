<?php

namespace App\Models\Restaurant;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_order_events';

    protected $fillable = [
        'tenant_id', 'order_id', 'order_item_id', 'user_id',
        'event', 'payload', 'ip_address',
    ];

    protected $casts = ['payload' => 'array'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

