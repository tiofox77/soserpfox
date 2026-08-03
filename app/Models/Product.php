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

    /**
     * Normaliza um valor de isenção para o CÓDIGO AGT (máx. 10 chars — cabe em
     * invoicing_*_items.tax_exemption_code, varchar(10)).
     *
     * O campo products.exemption_reason deve guardar o código (ex.: 'M04'), mas
     * histórico/pré-preenchimentos gravaram lá a DESCRIÇÃO ("Regime Especial de
     * Isenção (Artigo 53.º)", 40 chars) — o que fazia a venda falhar com
     * "Data too long for column 'tax_exemption_code'". Aqui aceitamos ambos:
     * código → devolve o código; descrição → resolve para o código respetivo.
     */
    public static function normalizeExemptionCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Já é um código AGT (M01/S02/I15/…): letra + dígitos, até 10 chars
        if (mb_strlen($value) <= 10 && preg_match('/^[A-Za-z]\d{1,3}$/', $value)) {
            return mb_strtoupper($value);
        }

        // É descrição: resolver via lista legada
        foreach (self::EXEMPTION_REASONS as $code => $reason) {
            if (mb_strtolower($reason) === mb_strtolower($value)) {
                return $code;
            }
        }

        // …ou via tabela oficial AGT
        try {
            $code = \App\Models\AGT\AGTTaxExemptionCode::whereRaw('LOWER(description) = ?', [mb_strtolower($value)])
                ->value('code');
            if ($code) {
                return $code;
            }
        } catch (\Throwable $e) {
            // tabela ainda não seedada — ignorar
        }

        // Desconhecido: nunca devolver texto longo (rebentaria a coluna).
        \Log::warning('Product::normalizeExemptionCode: valor de isenção não reconhecido', ['value' => $value]);
        return mb_strlen($value) <= 10 ? mb_strtoupper($value) : null;
    }

    /**
     * Descrição legível de um código/valor de isenção (para tax_exemption_reason).
     */
    public static function exemptionReasonText(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $code = self::normalizeExemptionCode($value);
        if ($code) {
            if (isset(self::EXEMPTION_REASONS[$code])) {
                return self::EXEMPTION_REASONS[$code];
            }
            try {
                $desc = \App\Models\AGT\AGTTaxExemptionCode::where('code', $code)->value('description');
                if ($desc) {
                    return mb_substr($desc, 0, 255);
                }
            } catch (\Throwable $e) {
                // ignorar
            }
        }

        // Sem correspondência: devolve o próprio texto (a coluna aceita 255).
        return mb_substr($value, 0, 255);
    }

    protected $fillable = [
        'tenant_id', 'category_id', 'brand_id', 'supplier_id',
        'type', 'code', 'sku', 'barcode', 'name', 'description', 'category',
        'featured_image', 'gallery',
        'price', 'cost', 'tax_type', 'tax_rate_id', 'exemption_reason',
        'manage_stock', 'stock_quantity', 'stock_min', 'stock_max', 'minimum_stock', 'unit', 'is_active',
        'track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale'
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'supplier_id' => 'integer',
        'tax_rate_id' => 'integer',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
        'track_batches' => 'boolean',
        'track_expiry' => 'boolean',
        'require_batch_on_purchase' => 'boolean',
        'require_batch_on_sale' => 'boolean',
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

        // Calcular o maior número usado por este tenant para o prefixo escolhido.
        // Usa CAST para ordenar numericamente (evita PROD000010 < PROD000002 em string).
        $maxNumber = (int) \App\Models\Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', $prefix . '%')
            ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)), 0) AS max_num', [strlen($prefix) + 1])
            ->value('max_num');

        $newNumber = $maxNumber + 1;

        // Defensivo: caso o código já exista (race condition / dados manuais), incrementar até ser livre
        $maxAttempts = 100;
        do {
            $code = $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
            $exists = \App\Models\Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->exists();
            if (!$exists) {
                return $code;
            }
            $newNumber++;
            $maxAttempts--;
        } while ($maxAttempts > 0);

        // Fallback final: incluir timestamp para garantir unicidade
        return $prefix . str_pad((string) (time() % 1000000), 6, '0', STR_PAD_LEFT);
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
    
    public function batches()
    {
        return $this->hasMany(\App\Models\Invoicing\ProductBatch::class);
    }
    
    public function activeBatches()
    {
        return $this->hasMany(\App\Models\Invoicing\ProductBatch::class)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->orderBy('expiry_date', 'asc');
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
    
    /**
     * Accessor: URL absoluta da imagem com domínio de produção
     * Garante que sempre use o domínio correto, mesmo em ambiente local
     * 
     * @return string|null
     */
    public function getImageUrlAttribute()
    {
        if (!$this->featured_image) {
            return null;
        }
        
        // Se a imagem já for uma URL completa (http:// ou https://), retornar como está
        if (filter_var($this->featured_image, FILTER_VALIDATE_URL)) {
            return $this->featured_image;
        }
        
        // Verificar se há um domínio de imagens configurado
        $imagesDomain = config('app.images_url', config('app.url'));
        
        // Se o caminho começar com '/', usar diretamente
        if (str_starts_with($this->featured_image, '/')) {
            return rtrim($imagesDomain, '/') . $this->featured_image;
        }
        
        // Caso contrário, usar Storage::url() mas forçar domínio absoluto
        $storagePath = \Storage::url($this->featured_image);
        
        // Garantir URL absoluta
        if (!str_starts_with($storagePath, 'http')) {
            return rtrim($imagesDomain, '/') . '/' . ltrim($storagePath, '/');
        }
        
        return $storagePath;
    }
}
