<?php

namespace App\Models\Accounting;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UMA FAMÍLIA DE BENS — «Viaturas», «Equipamento informático».
 *
 * O que ela guarda são as OMISSÕES da família: quantos anos dura, por que método
 * se amortiza e a que taxa. Uma viatura nova herda-as em vez de obrigar quem a
 * registou a saber a vida útil de cor.
 *
 * A tabela existia desde 2025 e não tinha modelo nem ecrã: o campo «Categoria»
 * do imobilizado apontava para uma lista que ninguém podia preencher.
 */
class FixedAssetCategory extends Model
{
    use BelongsToTenant;

    protected $table = 'fixed_asset_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'default_useful_life',
        'default_depreciation_method',
        'default_depreciation_rate',
    ];

    protected $casts = [
        'default_useful_life' => 'integer',
        'default_depreciation_rate' => 'decimal:2',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }
}
