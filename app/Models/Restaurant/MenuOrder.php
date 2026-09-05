<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Um pedido feito pelo cliente na carta online.
 *
 * NÃO é uma comanda — é um pedido à espera de um empregado. A diferença
 * importa: uma comanda exige turno aberto e mexe em stock e em caixa; isto
 * não mexe em nada até alguém o aceitar. Ver a migração para o porquê.
 */
class MenuOrder extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_menu_orders';

    protected $fillable = [
        'tenant_id', 'table_id', 'table_code', 'items', 'estimated_total',
        'customer_name', 'customer_phone', 'notes',
        'status', 'order_id', 'handled_by', 'handled_at',
    ];

    protected $casts = [
        'items'           => 'array',
        'estimated_total' => 'decimal:2',
        'handled_at'      => 'datetime',
    ];

    public function mesa()
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function comanda()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeAEspera($query)
    {
        return $query->where('status', 'pending');
    }
}
