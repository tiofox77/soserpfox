<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_stock_movements';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Tipos de movimento
    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Suspende SÓ a aplicação automática ao stock, não os eventos todos.
     *
     * Quase todos os sítios que criam movimentos já actualizaram o stock à mão
     * (a venda, a compra, a transferência, o POS) e usavam
     * `StockMovement::withoutEvents()` para o hook abaixo não voltar a debitar.
     * Só que `withoutEvents` desliga TODOS os observers — incluindo o de
     * auditoria. O resultado era que os movimentos que mais importam, os das
     * vendas, eram os únicos que não deixavam rasto na trilha.
     */
    protected static bool $ignorarAplicacaoAoStock = false;

    public static function semAplicarStock(callable $accao)
    {
        $anterior = static::$ignorarAplicacaoAoStock;
        static::$ignorarAplicacaoAoStock = true;

        try {
            return $accao();
        } finally {
            static::$ignorarAplicacaoAoStock = $anterior;
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($movement) {
            if (static::$ignorarAplicacaoAoStock) {
                return;
            }

            $movement->updateStock();
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

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Métodos
    public function updateStock()
    {
        switch ($this->type) {
            case self::TYPE_IN:
                Stock::addStock($this->warehouse_id, $this->product_id, $this->quantity, $this->unit_cost);
                break;

            case self::TYPE_OUT:
                Stock::removeStock($this->warehouse_id, $this->product_id, $this->quantity);
                break;

            case self::TYPE_TRANSFER:
                if ($this->from_warehouse_id && $this->to_warehouse_id) {
                    Stock::removeStock($this->from_warehouse_id, $this->product_id, $this->quantity);
                    Stock::addStock($this->to_warehouse_id, $this->product_id, $this->quantity, $this->unit_cost);
                }
                break;

            case self::TYPE_ADJUSTMENT:
                // Ajuste direto do stock
                $stock = Stock::where('warehouse_id', $this->warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->where('tenant_id', $this->tenant_id)
                    ->first();

                if ($stock) {
                    $stock->quantity = $this->quantity;
                    $stock->save();
                } else {
                    Stock::addStock($this->warehouse_id, $this->product_id, $this->quantity, $this->unit_cost);
                }
                break;
        }
    }

    public static function createEntry($data)
    {
        return static::create(array_merge($data, [
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'type' => self::TYPE_IN,
        ]));
    }

    public static function createExit($data)
    {
        return static::create(array_merge($data, [
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'type' => self::TYPE_OUT,
        ]));
    }

    public static function createTransfer($fromWarehouseId, $toWarehouseId, $productId, $quantity, $notes = null)
    {
        return static::create([
            'tenant_id' => activeTenantId(),
            'warehouse_id' => $fromWarehouseId,
            'product_id' => $productId,
            'type' => self::TYPE_TRANSFER,
            'quantity' => $quantity,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'user_id' => auth()->id(),
            'notes' => $notes,
        ]);
    }

    public static function createAdjustment($warehouseId, $productId, $newQuantity, $notes = null)
    {
        return static::create([
            'tenant_id' => activeTenantId(),
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'type' => self::TYPE_ADJUSTMENT,
            'quantity' => $newQuantity,
            'user_id' => auth()->id(),
            'notes' => $notes,
        ]);
    }
}
