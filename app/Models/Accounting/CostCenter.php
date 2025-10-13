<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'parent_id',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
