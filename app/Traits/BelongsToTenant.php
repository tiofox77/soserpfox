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
     * O FILTRO POR EMPRESA, ESCRITO À MÃO.
     *
     * O escopo global acima já o faz — mas SÓ com sessão aberta. Fora dela (um
     * comando de consola, uma tarefa agendada, um ensaio) não filtra nada, e
     * uma consulta que parecia segura passa a ver a casa toda.
     *
     * Este atalho diz o que quer, e diz-se a ler: `Evento::forTenant()`. Era
     * escrito à mão em vinte e tal modelos, cada um com a sua assinatura; os
     * que já o têm continuam com o seu — um método da classe manda sempre mais
     * do que um do trait.
     */
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where(
            $query->getModel()->getTable().'.tenant_id',
            $tenantId ?: activeTenantId()
        );
    }

    /**
     * Relacionamento com Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
