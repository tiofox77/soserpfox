<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma quebra: o artigo que expirou, se estragou, partiu ou se perdeu.
 *
 * Nunca se apaga — anula-se com o movimento de stock contrário. Uma perda
 * registada e depois apagada é uma perda escondida duas vezes.
 */
class Waste extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_wastes';

    protected $fillable = [
        'tenant_id', 'product_id', 'warehouse_id', 'quantity', 'reason', 'notes',
        'unit_cost', 'total_cost', 'stock_movement_id', 'reversal_movement_id',
        'annulled_at', 'annulled_by', 'source_module', 'user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'annulled_at' => 'datetime',
    ];

    /**
     * Lista FECHADA de motivos: cem «outros» não ensinam nada. O campo de
     * notas é onde cabe o resto da história.
     */
    public const MOTIVOS = [
        'expirado' => 'Expirado / fora de validade',
        'estragado' => 'Estragado / deteriorado',
        'partido' => 'Partido / danificado',
        'perdido' => 'Perdido / extraviado',
        'uso_interno' => 'Uso interno / consumo próprio',
        'outro' => 'Outro',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::MOTIVOS[$this->reason] ?? $this->reason;
    }

    public function anulada(): bool
    {
        return $this->annulled_at !== null;
    }
}
