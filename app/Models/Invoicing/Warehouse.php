<?php

namespace App\Models\Invoicing;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'invoicing_warehouses';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'location',
        'address',
        'city',
        'postal_code',
        'phone',
        'email',
        'manager_id',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // Relacionamentos
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    // Métodos
    public function getStockQuantity($productId)
    {
        return $this->stocks()
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    public function hasStock($productId, $quantity)
    {
        return $this->getStockQuantity($productId) >= $quantity;
    }

    public function setAsDefault()
    {
        // Remove default de outros armazéns do mesmo tenant
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Obtém o armazém padrão do tenant ativo
     */
    public static function getDefault($tenantId = null)
    {
        $tenantId = $tenantId ?? activeTenantId();
        
        return static::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Obtém ou cria um armazém padrão
     */
    public static function getOrCreateDefault($tenantId = null)
    {
        $tenantId = $tenantId ?? activeTenantId();
        $warehouse = static::getDefault($tenantId);
        
        if (!$warehouse) {
            // Verifica se tem algum armazém ativo
            $warehouse = static::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();
            
            if ($warehouse) {
                // Define o primeiro ativo como padrão
                $warehouse->setAsDefault();
            } else {
                // Cria um novo armazém padrão
                $warehouse = static::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Armazém Principal',
                    'code' => 'ARM-001-' . $tenantId,
                    'location' => 'Sede',
                    'is_active' => true,
                    'is_default' => true,
                ]);
            }
        }
        
        return $warehouse;
    }

    /**
     * Verifica se é o armazém padrão
     */
    public function isDefault()
    {
        return $this->is_default;
    }
}
