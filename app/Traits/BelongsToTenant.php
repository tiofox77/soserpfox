<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToTenant()
    {
        // Ao criar, define automaticamente o tenant_id
        static::creating(function (Model $model) {
            if (!$model->tenant_id && auth()->check()) {
                $model->tenant_id = activeTenantId();
            }
        });
        
        // Global scope para filtrar sempre pelo tenant ativo
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && activeTenantId()) {
                $builder->where('tenant_id', activeTenantId());
            }
        });
    }
    
    /**
     * Relacionamento com Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
