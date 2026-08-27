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

    /**
     * Torna este o armazém padrão da empresa — nos DOIS sítios que o guardam.
     *
     * `invoicing_warehouses.is_default` é o que todos os formulários lêem
     * (facturas, orçamentos, compras, POS, SAFT). `invoicing_settings.
     * default_warehouse_id` é o que o ecrã de definições mostra e o que o
     * módulo de restaurante usa. Enquanto viveram separados, mudar um deixava
     * o outro a mentir: escolher o armazém principal nas definições não fazia
     * efeito nenhum, e marcá-lo em Armazéns deixava as definições a mostrar o
     * anterior.
     *
     * Os dois passam por aqui. Um dia a coluna das definições pode
     * desaparecer; até lá, é este método que as mantém a dizer o mesmo.
     */
    public function setAsDefault()
    {
        // Remove default de outros armazéns do mesmo tenant
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);

        // Nunca pode partir a marcação do armazém: o `is_default` acima é o
        // que faz o sistema funcionar, e é esse que tem de ficar de pé.
        try {
            InvoicingSettings::where('tenant_id', $this->tenant_id)
                ->update(['default_warehouse_id' => $this->id]);
        } catch (\Throwable $e) {
            \Log::warning('Armazém padrão: falhou a sincronia com as definições', [
                'armazem'   => $this->id,
                'tenant_id' => $this->tenant_id,
                'erro'      => $e->getMessage(),
            ]);
        }
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

    /**
     * Garante que o tenant tem um armazém principal/default.
     * Scope-safe: ignora o global scope de tenant (usado em hooks de criação
     * de tenant e em backfills, onde activeTenantId() pode não ser o alvo).
     *
     * @return static O armazém default do tenant.
     */
    public static function ensureDefaultForTenant(int $tenantId): self
    {
        // Já existe um default activo?
        $default = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        if ($default) {
            return $default;
        }

        // Existe algum armazém activo? Promover o primeiro a default.
        $any = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        if ($any) {
            static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', '!=', $any->id)
                ->update(['is_default' => false]);
            $any->update(['is_default' => true]);
            return $any;
        }

        // Nenhum armazém — criar o principal.
        return static::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Armazém Principal',
            'code'        => 'ARM-001-' . $tenantId,
            'location'    => 'Sede',
            'description' => 'Armazém principal padrão',
            'is_active'   => true,
            'is_default'  => true,
        ]);
    }
}
