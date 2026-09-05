<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrada na fila de espera: quem chegou sem reserva e está à porta.
 *
 * `left` importa tanto como `seated` — a diferença entre os dois é quantos
 * clientes a casa perde por falta de mesa.
 */
class Waitlist extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_waitlist';

    public const ESTADOS = [
        'waiting' => 'À espera',
        'seated' => 'Sentado',
        'left' => 'Foi-se embora',
    ];

    protected $fillable = [
        'tenant_id', 'venue_id', 'guest_name', 'phone', 'guest_count',
        'status', 'notes', 'table_id', 'order_id',
        'arrived_at', 'resolved_at', 'created_by',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Há quanto tempo esta pessoa espera, em minutos. */
    public function minutosDeEspera(): int
    {
        $fim = $this->resolved_at ?? now();

        return (int) floor($this->arrived_at->diffInMinutes($fim));
    }
}
