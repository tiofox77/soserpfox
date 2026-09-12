<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A AMORTIZAÇÃO DE UM PERÍODO — uma linha por bem e por mês.
 *
 * Nasce em RASCUNHO: calcular não é lançar. O cálculo diz quanto é; lançar mete
 * o valor na contabilidade, e é aí que o `move_id` aparece. Enquanto for
 * rascunho, recalcular pode substituí-la; depois de lançada, não — o lançamento
 * já conta nos saldos e desfaz-se por estorno como qualquer outro.
 *
 * A tabela não tem `tenant_id`: pendura do bem, que tem. O escopo faz-se por
 * ele.
 */
class FixedAssetDepreciation extends Model
{
    protected $table = 'fixed_asset_depreciations';

    protected $fillable = [
        'fixed_asset_id',
        'period_id',
        'depreciation_date',
        'depreciation_amount',
        'accumulated_depreciation',
        'book_value',
        'move_id',
        'status',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'depreciation_amount' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class, 'move_id');
    }
}
