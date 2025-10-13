<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class AnalyticDimension extends Model
{
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
