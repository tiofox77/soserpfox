<?php

namespace App\Models\AGT;

use Illuminate\Database\Eloquent\Model;

/**
 * Códigos pautais para Imposto Especial sobre Consumo (IEC).
 * DS.120 Anexo 9.7 — usado quando `taxType=IEC` e `taxCode=<código pautal>`.
 *
 * Lista parcial dos códigos mais relevantes para o mercado angolano.
 * A lista completa é mantida pela AGT/Aduana e deve ser actualizada via CSV.
 */
class AGTIecPautalCode extends Model
{
    protected $table = 'agt_iec_pautal_codes';

    protected $fillable = [
        'pautal_code',
        'description',
        'category',
        'rate_percentage',
        'specific_amount',
        'unit_of_measure',
        'is_active',
    ];

    protected $casts = [
        'rate_percentage' => 'decimal:2',
        'specific_amount' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public const CAT_ALCOHOL    = 'bebida_alcoolica';
    public const CAT_TOBACCO    = 'tabaco';
    public const CAT_FUEL       = 'combustivel';
    public const CAT_VEHICLE    = 'veiculo';
    public const CAT_LUXURY     = 'luxo';
    public const CAT_BEVERAGE   = 'bebida_nao_alcoolica';
    public const CAT_OTHER      = 'outro';

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForCategory($q, string $category)
    {
        return $q->where('category', $category);
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('pautal_code', trim($code))->first();
    }
}
