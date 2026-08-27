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
                // Coluna QUALIFICADA com a tabela do modelo. Sem isto, qualquer
                // consulta com join a outra tabela que também tenha `tenant_id`
                // (clientes, fornecedores, produtos — quase todas) rebentava com
                // "Column 'tenant_id' in where clause is ambiguous". O filtro
                // por empresa fica exactamente igual; só passa a dizer de que
                // tabela é que fala.
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    activeTenantId()
                );
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
