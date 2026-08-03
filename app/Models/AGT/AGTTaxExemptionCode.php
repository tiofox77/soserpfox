<?php

namespace App\Models\AGT;

use Illuminate\Database\Eloquent\Model;

/**
 * Códigos de isenção AGT (DS.120 §4.1).
 *
 * - IVA: M01..M93 (Anexo 9.1)
 * - IS : S01..S03 (Anexo 9.2)
 * - IEC: I01..I16 (Anexo 9.3)
 * - NS : códigos de isenção de não sujeição
 */
class AGTTaxExemptionCode extends Model
{
    protected $table = 'agt_tax_exemption_codes';

    protected $fillable = [
        'tax_type',
        'code',
        'description',
        'legal_basis',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const TAX_TYPE_IVA = 'IVA';
    public const TAX_TYPE_IS  = 'IS';
    public const TAX_TYPE_IEC = 'IEC';
    public const TAX_TYPE_NS  = 'NS';

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForType($q, string $taxType)
    {
        return $q->where('tax_type', strtoupper($taxType));
    }

    /** Procura código por `code`. Devolve null se inexistente. */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }

    /** Lista de pares [code => description] para selects. */
    public static function selectFor(string $taxType): array
    {
        return static::query()
            ->active()
            ->forType($taxType)
            ->orderBy('code')
            ->pluck('description', 'code')
            ->toArray();
    }
}
