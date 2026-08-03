<?php

namespace App\Models\Invoicing;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $table = 'invoicing_taxes';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'rate',
        'type',
        'saft_code',
        'saft_type',
        'exemption_code',
        'exemption_reason',
        'is_default',
        'is_active',
        'include_in_price',
        'compound_tax',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'include_in_price' => 'boolean',
        'compound_tax' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'tax_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Helper methods
    public static function getDefaultTax($tenantId)
    {
        return static::forTenant($tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Conjunto canónico de impostos de Angola (IVA + SAFT-AO + IRT).
     * Fonte única de verdade — usada na criação do tenant e no backfill.
     */
    /**
     * Código AGT de isenção esperado para um imposto do conjunto padrão
     * (ex.: 'IVAISEN' → 'M01'). Usado para alinhar regimes antigos que só
     * tinham a descrição gravada. Devolve null para taxas não isentas.
     */
    public static function defaultExemptionCodeFor(?string $taxCode): ?string
    {
        if (!$taxCode) {
            return null;
        }
        foreach (static::defaultSet() as $tax) {
            if (strcasecmp($tax['code'], $taxCode) === 0) {
                return $tax['exemption_code'] ?? null;
            }
        }
        // Regime de exclusão (criado pelo TaxRegimeSyncer, fora do defaultSet)
        return strcasecmp($taxCode, 'ISENTO-EXCL') === 0 ? 'M04' : null;
    }

    public static function defaultSet(): array
    {
        return [
            ['code' => 'IVA14',   'name' => 'IVA 14% (Normal)',       'rate' => 14.00, 'type' => 'iva', 'saft_code' => 'NOR', 'saft_type' => 'NOR', 'exemption_reason' => null, 'is_default' => true,  'description' => 'Imposto sobre o Valor Acrescentado - Taxa Normal Angola'],
            ['code' => 'IVA7',    'name' => 'IVA 7% (Reduzida)',      'rate' => 7.00,  'type' => 'iva', 'saft_code' => 'RED', 'saft_type' => 'RED', 'exemption_reason' => null, 'is_default' => false, 'description' => 'IVA - Taxa Reduzida (produtos essenciais, medicamentos, etc.)'],
            ['code' => 'IVA5',    'name' => 'IVA 5% (Reduzida)',      'rate' => 5.00,  'type' => 'iva', 'saft_code' => 'RED', 'saft_type' => 'RED', 'exemption_reason' => null, 'is_default' => false, 'description' => 'IVA - Taxa Reduzida 5% (bens da cesta básica)'],
            ['code' => 'IVA0',    'name' => 'IVA 0% (Exportação)',    'rate' => 0.00,  'type' => 'iva', 'saft_code' => 'NOR', 'saft_type' => 'NOR', 'exemption_code' => 'M04', 'exemption_reason' => 'Exportação de bens para fora do território nacional', 'is_default' => false, 'description' => 'Taxa zero para exportações e operações específicas'],
            ['code' => 'IVAISEN', 'name' => 'Isento de IVA',          'rate' => 0.00,  'type' => 'iva', 'saft_code' => 'ISE', 'saft_type' => 'ISE', 'exemption_code' => 'M01', 'exemption_reason' => 'Isento nos termos do Código do IVA - Artigo 9º', 'is_default' => false, 'description' => 'Operações isentas de IVA (saúde, educação, serviços financeiros, etc.)'],
            ['code' => 'IVANS',   'name' => 'Não Sujeito a IVA',      'rate' => 0.00,  'type' => 'iva', 'saft_code' => 'NS',  'saft_type' => 'NS',  'exemption_code' => 'M99', 'exemption_reason' => 'Operação fora do âmbito do IVA', 'is_default' => false, 'description' => 'Operações não sujeitas ao regime de IVA'],
            ['code' => 'IRT6.5',  'name' => 'IRT 6,5% (Retenção)',    'rate' => 6.50,  'type' => 'irt', 'saft_code' => 'OUT', 'saft_type' => 'OUT', 'exemption_reason' => null, 'is_default' => false, 'description' => 'Imposto sobre Rendimento do Trabalho - Retenção na fonte (serviços)'],
        ];
    }

    /**
     * Cria/garante o conjunto padrão de impostos para um tenant (idempotente
     * por código). Não duplica e garante que existe exatamente um is_default.
     */
    public static function seedDefaultsForTenant($tenantId): int
    {
        $created = 0;
        foreach (static::defaultSet() as $tax) {
            $existing = static::where('tenant_id', $tenantId)->where('code', $tax['code'])->first();
            if ($existing) {
                continue;
            }
            static::create(array_merge($tax, [
                'tenant_id'        => $tenantId,
                'is_active'        => true,
                'include_in_price' => false,
                'compound_tax'     => false,
                // Só marcar como default se ainda não houver nenhum default no tenant
                'is_default'       => $tax['is_default']
                    && !static::where('tenant_id', $tenantId)->where('is_default', true)->exists(),
            ]));
            $created++;
        }

        // Garantir que existe um default (se nenhum, marcar o IVA14)
        if (!static::where('tenant_id', $tenantId)->where('is_default', true)->exists()) {
            static::where('tenant_id', $tenantId)->where('code', 'IVA14')
                ->update(['is_default' => true]);
        }

        return $created;
    }

    public function getFormattedRateAttribute()
    {
        return $this->rate . '%';
    }

    public function getSaftTypeNameAttribute()
    {
        $types = [
            'NOR' => 'Normal',
            'RED' => 'Reduzida',
            'ISE' => 'Isento',
            'NS' => 'Não Sujeito',
            'OUT' => 'Outro',
        ];

        return $types[$this->saft_type] ?? '-';
    }
}
