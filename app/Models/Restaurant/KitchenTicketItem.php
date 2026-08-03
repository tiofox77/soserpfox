<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketItem extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_kitchen_ticket_items';
    protected $fillable = ['tenant_id', 'ticket_id', 'order_item_id', 'status'];

    public function ticket(): BelongsTo { return $this->belongsTo(KitchenTicket::class, 'ticket_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
}
