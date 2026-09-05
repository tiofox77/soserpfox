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
'preco_no_pos',
                'manage_stock', 'stock_quantity', 'stock_min', 'stock_max', 'minimum_stock', 'unit', 'is_active',
        'track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale',
        // Farmácia
        'requires_prescription', 'is_controlled', 'active_ingredient', 'dosage',
        'pharmaceutical_form', 'armed_registration',
        // Vestuário
        'size', 'color', 'gender', 'material',
        // Cosmética e mercearia (net_content é dos dois: é o que separa duas
        // embalagens do mesmo produto). A cor serve de tom na cosmética e já
        // está acima — não há campo novo para a mesma coisa.
        'net_content', 'pao_months', 'inci_ingredients',
        'storage_conditions', 'allergens', 'origin_country',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'supplier_id' => 'integer',
        'tax_rate_id' => 'integer',
        'preco_no_pos' => 'boolean',
        'manage_stock' => 'boolean',
        'is_active' => 'boolean',
        'track_batches' => 'boolean',
        'track_expiry' => 'boolean',
        'require_batch_on_purchase' => 'boolean',
        'require_batch_on_sale' => 'boolean',
        'requires_prescription' => 'boolean',
        'is_controlled' => 'boolean',
        // Vem do formulário como string; sem o cast, "12" != 12 e comparações
        // como $p->pao_months > 6 dependiam do acaso da conversão do PHP.
        'pao_months' => 'integer',
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
    /**
     * Este artigo controla stock?
     *
     * Só controla quando é um PRODUTO com "Gerenciar Stock" ligado. Serviços
     * e produtos com o stock desligado vendem-se sempre, sem validação de
     * disponibilidade e sem baixar stock — é a regra única usada no POS, na
     * venda web e na sincronização offline.
     */
    public function controlaStock(): bool
    {
        // Rastrear lotes É controlar stock: um artigo com lotes desconta e
        // consome-os mesmo que "Gerenciar Stock" não esteja explicitamente
        // ligado. Serviços nunca controlam.
        return ($this->type !== 'servico')
            && ((bool) $this->manage_stock || (bool) $this->track_batches);
    }

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
    
    /*
     |--------------------------------------------------------------------------
     | Filtros de farmácia, vestuário, cosmética e mercearia
     |--------------------------------------------------------------------------
     | Filtros puros de coluna: encadeiam-se sobre uma query JÁ limitada à
     | empresa. Este modelo não tem scope global de tenant (ver
     | generateProductCode, que filtra à mão), portanto quem chama continua
     | obrigado ao where('tenant_id', activeTenantId()) — pôr o filtro aqui
     | dentro escondia-o e partia as consultas de plataforma.
     */

    /** Artigos que só saem do balcão contra receita médica. */
    public function scopeComReceita($query)
    {
        return $query->where('requires_prescription', true);
    }

    /** Psicotrópicos e estupefacientes, sujeitos a registo próprio. */
    public function scopeControlados($query)
    {
        return $query->where('is_controlled', true);
    }

    /**
     * Pesquisa pela substância activa — a pergunta que a farmácia faz todos os
     * dias ("o que tenho com paracetamol?"), a que o nome comercial não responde.
     *
     * O termo é escapado: um `%` escrito pelo balconista é para procurar um
     * `%`, não para alargar a pesquisa ao catálogo inteiro.
     */
    public function scopePorSubstancia($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        $escapado = addcslashes($termo, '%_\\');

        return $query->where('active_ingredient', 'like', "%{$escapado}%");
    }

    /** Vestuário: um tamanho concreto (S, M, 38…). */
    public function scopePorTamanho($query, ?string $tamanho)
    {
        $tamanho = trim((string) $tamanho);
        if ($tamanho === '') {
            return $query;
        }

        return $query->where('size', $tamanho);
    }

    /** Vestuário: uma cor concreta. */
    public function scopePorCor($query, ?string $cor)
    {
        $cor = trim((string) $cor);
        if ($cor === '') {
            return $query;
        }

        return $query->where('color', $cor);
    }

    /**
     * Mercearia: o que vai à câmara e o que fica na prateleira.
     *
     * Existe para o STOCK e não só para a ficha: quem recebe uma palete precisa
     * de saber o que vai ao frio antes de abrir artigo a artigo — passado esse
     * momento, o prejuízo já está feito.
     *
     * O valor é comparado tal e qual (ambiente | refrigerado | congelado): é uma
     * lista fechada e não uma pesquisa livre.
     */
    public function scopePorConservacao($query, ?string $modo)
    {
        $modo = trim((string) $modo);
        if ($modo === '') {
            return $query;
        }

        return $query->where('storage_conditions', $modo);
    }

    /**
     * Mercearia: "isto leva glúten?", a pergunta de balcão que se faz com o
     * cliente à espera.
     *
     * Pesquisa por dentro do texto porque os alergénios vêm numa lista ("glúten,
     * soja, frutos de casca rija") e ninguém procura pela lista inteira.
     *
     * O termo é escapado: um `%` escrito pelo balconista é para procurar um `%`,
     * não para alargar a pesquisa ao catálogo inteiro.
     */
    public function scopeComAlergenio($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        $escapado = addcslashes($termo, '%_\\');

        return $query->where('allergens', 'like', "%{$escapado}%");
    }

    /**
     * Accessor: quanto tempo o produto dura DEPOIS de aberto (PAO).
     *
     * É o símbolo do frasco aberto com "12M" no rótulo, e não se confunde com o
     * prazo de validade: um creme por abrir dura até à data do rótulo, aberto
     * dura estes meses. São dois números diferentes e a loja precisa dos dois.
     *
     * A frase montada mora aqui porque se repete ao cliente ao balcão e aparece
     * na ficha, na etiqueta e na lista — escrita à mão em cada ecrã, mais tarde
     * ou mais cedo diziam coisas diferentes.
     */
    public function getValidadeAposAberturaAttribute(): ?string
    {
        $meses = (int) $this->pao_months;

        // Sem PAO não há frase nenhuma: quem chama distingue "não se aplica" de
        // "aplica-se e são zero meses", que não existe.
        if ($meses < 1) {
            return null;
        }

        return $meses === 1
            ? __('1 mês após abertura')
            : __(':meses meses após abertura', ['meses' => $meses]);
    }

    /**
     * Accessor: como o medicamento se identifica ao balcão.
     *
     * "Paracetamol" sozinho não chega — há os comprimidos de 500mg e o xarope,
     * e são artigos diferentes com stock diferente. Junta o que existir e
     * ignora o resto, para os artigos que não são medicamentos devolverem
     * simplesmente o nome.
     */
    public function getDescricaoFarmaceuticaAttribute(): string
    {
        $partes = array_filter(
            [$this->name, $this->dosage, $this->pharmaceutical_form],
            fn ($parte) => trim((string) $parte) !== ''
        );

        return implode(' ', array_map(fn ($parte) => trim((string) $parte), $partes));
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
