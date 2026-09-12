<?php

namespace App\Models\Accounting;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UM BEM DO IMOBILIZADO — uma viatura, uma máquina, um computador.
 *
 * A TABELA EXISTIA DESDE 2025 E NUNCA RECEBEU UMA LINHA: o ecrã em Livewire
 * tinha o formulário todo e o `save()` só dizia «Ativo salvo com sucesso!
 * (Funcionalidade completa será implementada em breve)» — não gravava nada. A
 * lista era um paginador vazio construído à mão, e os quatro totais eram zeros
 * literais. Quem lá entrasse registava bens que desapareciam sem aviso.
 *
 * AS TRÊS CONTAS não são enfeite: é com elas que a amortização se lança. O bem
 * está numa conta de activo; a amortização do período vai a DÉBITO da conta de
 * gasto e a CRÉDITO da de amortizações acumuladas, que é a que desconta o activo
 * no balanço.
 */
class FixedAsset extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'fixed_assets';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'category_id',
        'account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'acquisition_date',
        'acquisition_value',
        'residual_value',
        'useful_life_years',
        'depreciation_method',
        'depreciation_rate',
        'accumulated_depreciation',
        'book_value',
        'status',
        'disposal_date',
        'disposal_value',
        'location',
        'serial_number',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'disposal_value' => 'decimal:2',
        'depreciation_rate' => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** A conta de GASTO onde a amortização do período é debitada. */
    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    /** A conta que ACUMULA as amortizações e desconta o activo no balanço. */
    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class, 'fixed_asset_id');
    }

    /**
     * O QUE HÁ PARA AMORTIZAR: o valor de aquisição menos o residual.
     *
     * O RESIDUAL NÃO SE AMORTIZA — é o que se espera valer no fim da vida útil.
     * Amortizar o valor todo levava o bem a zero e punha no gasto dinheiro que o
     * bem ainda vale.
     */
    public function baseAmortizavel(): float
    {
        return max(0.0, round((float) $this->acquisition_value - (float) $this->residual_value, 2));
    }

    /** Quanto ainda falta amortizar. */
    public function porAmortizar(): float
    {
        return max(0.0, round($this->baseAmortizavel() - (float) $this->accumulated_depreciation, 2));
    }
}
