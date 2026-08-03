<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenTicket extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_kitchen_tickets';
    protected $fillable = ['tenant_id', 'venue_id', 'station_id', 'order_id', 'ticket_number', 'status', 'priority', 'queued_at', 'accepted_at', 'ready_at', 'served_at'];
    protected $casts = ['queued_at' => 'datetime', 'accepted_at' => 'datetime', 'ready_at' => 'datetime', 'served_at' => 'datetime'];

    public function station(): BelongsTo { return $this->belongsTo(KitchenStation::class, 'station_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): HasMany { return $this->hasMany(KitchenTicketItem::class, 'ticket_id'); }
}
