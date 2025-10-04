<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_stocks';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reserved_quantity' => 'decimal:3',
        'available_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($stock) {
            $stock->available_quantity = $stock->quantity - $stock->reserved_quantity;
        });
    }

    // Relacionamentos
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Métodos
    public static function addStock($warehouseId, $productId, $quantity, $unitCost = null)
    {
        $stock = static::firstOrCreate(
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'tenant_id' => activeTenantId(),
            ],
            [
                'quantity' => 0,
                'unit_cost' => $unitCost,
            ]
        );

        // Atualiza custo médio ponderado
        if ($unitCost && $unitCost > 0) {
            $totalCost = ($stock->quantity * $stock->unit_cost) + ($quantity * $unitCost);
            $totalQty = $stock->quantity + $quantity;
            $stock->unit_cost = $totalQty > 0 ? $totalCost / $totalQty : $unitCost;
        }

        $stock->quantity += $quantity;
        $stock->save();

        return $stock;
    }

    public static function removeStock($warehouseId, $productId, $quantity)
    {
        $stock = static::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('tenant_id', activeTenantId())
            ->first();

        if (!$stock || $stock->available_quantity < $quantity) {
            throw new \Exception('Stock insuficiente');
        }

        $stock->quantity -= $quantity;
        $stock->save();

        return $stock;
    }

    public function reserve($quantity)
    {
        if ($this->available_quantity < $quantity) {
            throw new \Exception('Stock disponível insuficiente');
        }

        $this->reserved_quantity += $quantity;
        $this->save();
    }

    public function release($quantity)
    {
        $this->reserved_quantity -= $quantity;
        if ($this->reserved_quantity < 0) {
            $this->reserved_quantity = 0;
        }
        $this->save();
    }
}
