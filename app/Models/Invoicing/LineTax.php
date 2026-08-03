<?php

namespace App\Models\Invoicing;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Imposto ADICIONAL de uma linha: IEC (Imposto Especial de Consumo) ou
 * IS (Imposto de Selo). O IVA continua nas colunas da própria linha.
 *
 * A AGT aceita `taxes[]` como array por linha — é aqui que os extras vivem.
 */
class LineTax extends Model
{
    use BelongsToTenant;

    protected $table = 'invoicing_line_taxes';

    public const TIPO_IEC = 'IEC';
    public const TIPO_IS  = 'IS';

    /** Tipos que esta tabela representa. O IVA NÃO entra aqui. */
    public const TIPOS = [
        self::TIPO_IEC => 'Imposto Especial de Consumo',
        self::TIPO_IS  => 'Imposto de Selo',
    ];

    protected $fillable = [
        'tenant_id',
        'line_type',
        'line_id',
        'tax_type',
        'tax_country_region',
        'tax_code',
        'tax_percentage',
        'tax_amount',
        'pautal_code',
        'verba_no',
        'description',
        'tax_exemption_code',
        'tax_exemption_reason',
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:4',
        'tax_amount'     => 'decimal:2',
        // verba é TEXTO: as verbas reais têm subníveis (23.3, 16.2.4)
        'verba_no'       => 'string',
    ];

    public function line(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'line_type', 'line_id');
    }

    /** Estrutura pronta para o array `taxes[]` do payload AGT. */
    public function toAgtTax(): array
    {
        $tax = [
            'taxType'          => $this->tax_type,
            'taxCountryRegion' => $this->tax_country_region ?: 'AO',
            'taxCode'          => $this->tax_code ?: 'NOR',
            'taxPercentage'    => round((float) $this->tax_percentage, 2),
            'taxContribution'  => round((float) $this->tax_amount, 2),
        ];

        // ── Imposto de Selo ───────────────────────────────────────────────
        // Regra do modelo SAF-T que a AGT segue (FAQ oficial do Portal das
        // Finanças, aplicável ao DS.120):
        //   · taxCode = a VERBA da Tabela anexa, com subníveis (23.3, 16.2.4)
        //   · verba ad valorem → taxPercentage
        //   · verba de valor fixo → taxAmount (montante unitário)
        // Os dois são alternativas, não se enviam juntos.
        if ($this->tax_type === self::TIPO_IS) {
            $tax['taxCode'] = (string) ($this->verba_no ?: $this->tax_code ?: 'OUT');

            if ((float) $this->tax_percentage > 0) {
                $tax['taxPercentage'] = round((float) $this->tax_percentage, 2);
                unset($tax['taxAmount']);
            } else {
                $tax['taxAmount'] = round((float) $this->tax_amount, 2);
                unset($tax['taxPercentage']);
            }
        }

        if (filled($this->tax_exemption_code)) {
            $tax['taxExemptionCode'] = $this->tax_exemption_code;
        }
        if (filled($this->tax_exemption_reason)) {
            $tax['taxExemptionReason'] = $this->tax_exemption_reason;
        }

        return $tax;
    }

    /** Impostos extra de uma linha, prontos para o payload. */
    public static function agtTaxesForLine(Model $line): array
    {
        return static::where('line_type', get_class($line))
            ->where('line_id', $line->id)
            ->orderBy('id')
            ->get()
            ->map(fn (self $t) => $t->toAgtTax())
            ->all();
    }
}
