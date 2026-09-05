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
        'customer_name', 'customer_phone', 'delivery_address', 'delivery_fee',
        'dispatched_at', 'tip_amount',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'closed_at' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    /**
     * Os canais por que uma comanda pode entrar.
     *
     * Os quatro estão na base desde o início; os dois últimos não tinham por
     * onde ser escolhidos. Estão aqui em vez de espalhados pelos ecrãs para
     * haver UMA lista — a divergência entre listas não dá erro nenhum, só um
     * canal que aparece num sítio e não no outro.
     */
    public const CANAIS = [
        'table' => 'Mesa',
        'counter' => 'Balcão',
        'takeaway' => 'Take-away',
        'delivery' => 'Entrega',
    ];

    /** Os que se escolhem à mão: a mesa vem de se tocar numa mesa. */
    public const CANAIS_SEM_MESA = ['counter', 'takeaway', 'delivery'];

    public function getChannelLabelAttribute(): string
    {
        return self::CANAIS[$this->channel] ?? $this->channel;
    }

    /** Uma venda para fora: não ocupa mesa e tem de dizer para quem é. */
    public function paraFora(): bool
    {
        return in_array($this->channel, ['takeaway', 'delivery'], true);
    }

    /**
     * A taxa de entrega já foi para alguma factura desta comanda?
     *
     * Numa conta dividida saem várias facturas da mesma comanda. O transporte
     * é um só — cobrá-lo em cada uma seria cobrá-lo tantas vezes quantas as
     * pessoas que dividem a conta.
     */
    public function deliveryJaFacturada(): bool
    {
        return OrderItemBilling::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->where('order_id', $this->id)
            ->exists();
    }

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

