<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class AnalyticDimension extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'is_mandatory',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function tags()
    {
        return $this->hasMany(AnalyticTag::class, 'dimension_id');
    }
}
