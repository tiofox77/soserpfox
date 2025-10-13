<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class AnalyticTag extends Model
{
    protected $fillable = [
        'dimension_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function dimension()
    {
        return $this->belongsTo(AnalyticDimension::class);
    }
}
