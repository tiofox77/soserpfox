<?php

namespace App\Models\Restaurant;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    public const OPEN_STATUSES = [
        'draft', 'confirmed', 'in_preparation', 'ready', 'served', 'partially_billed',
    ];

    protected $table = 'restaurant_orders';

    protected $fillable = [
        'tenant_id', 'venue_id', 'table_id', 'client_id', 'waiter_id',
        'order_number', 'local_uuid', 'channel', 'status', 'guest_count',
        'subtotal', 'discount_total', 'tax_total', 'grand_total', 'notes',
        'confirmed_at', 'closed_at', 'closed_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function table(): BelongsTo { return $this->belongsTo(DiningTable::class, 'table_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function waiter(): BelongsTo { return $this->belongsTo(User::class, 'waiter_id'); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function events(): HasMany { return $this->hasMany(OrderEvent::class); }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}

