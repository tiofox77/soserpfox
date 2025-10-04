<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_products';

    // Motivos de Isenção de IVA em Angola (AGT)
    public const EXEMPTION_REASONS = [
        'M01' => 'Artigo 9.º, n.º 1 - Operações isentas',
        'M02' => 'Artigo 12.º - Transmissão de bens e prestação de serviços isentas',
        'M04' => 'Regime Especial de Isenção (Artigo 53.º)',
        'M10' => 'Bens de primeira necessidade',
        'M11' => 'Produtos farmacêuticos e equipamentos médicos',
        'M12' => 'Transportes de passageiros',
        'M13' => 'Serviços de educação',
        'M14' => 'Serviços de saúde',
        'M15' => 'Operações financeiras e seguros',
        'M16' => 'Operações imobiliárias isentas',
        'M99' => 'Outros motivos de isenção',
    ];

    protected $fillable = [
        'tenant_id', 'category_id', 'brand_id', 'supplier_id',
        'type', 'code', 'sku', 'barcode', 'name', 'description', 'category',
        'featured_image', 'gallery',
        'price', 'cost', 'tax_type', 'tax_rate_id', 'exemption_reason',
        'manage_stock', 'stock_quantity', 'stock_min', 'stock_max', 'minimum_stock', 'unit', 'is_active'
    ];

    protected $casts = [
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'gallery' => 'array',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->code)) {
                $product->code = static::generateProductCode($product->tenant_id, $product->type);
            }
        });
    }
    
    public static function generateProductCode($tenantId, $type = 'produto')
    {
        // Define o prefixo baseado no tipo
        $prefix = $type === 'servico' ? 'SVC' : 'PROD';
        
        $lastProduct = static::where('tenant_id', $tenantId)
            ->where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        $newNumber = $lastProduct ? ((int) substr($lastProduct->code, strlen($prefix))) + 1 : 1;
        
        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(\App\Models\Invoicing\Tax::class, 'tax_rate_id');
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stocks()
    {
        return $this->hasMany(\App\Models\Invoicing\Stock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(\App\Models\Invoicing\StockMovement::class);
    }
    
    // Accessor: Preço com Taxa
    public function getPriceWithTaxAttribute()
    {
        if ($this->tax_type === 'iva' && $this->taxRate) {
            return $this->price * (1 + ($this->taxRate->rate / 100));
        }
        return $this->price;
    }
    
    // Accessor: Valor da Taxa
    public function getTaxAmountAttribute()
    {
        if ($this->tax_type === 'iva' && $this->taxRate) {
            return $this->price * ($this->taxRate->rate / 100);
        }
        return 0;
    }
    
    // Accessor: Preço de Venda (alias de price)
    public function getSalePriceAttribute()
    {
        return $this->price ?? 0;
    }
}
