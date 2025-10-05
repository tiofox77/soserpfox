<?php

namespace App\Support;

use Spatie\Permission\Contracts\PermissionsTeamResolver;

class TenantTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    /**
     * Set the team id for teams/groups support
     */
    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof \Illuminate\Database\Eloquent\Model) {
            $id = $id->getKey();
        }
        $this->teamId = $id;
    }

    /**
     * Get the team id
     * Se não foi definido manualmente, retorna o tenant_id do usuário autenticado
     */
    public function getPermissionsTeamId(): int|string|null
    {
        // Se foi definido manualmente, usar esse valor
        if ($this->teamId !== null) {
            return $this->teamId;
        }
        
        // Caso contrário, usar o tenant_id do usuário autenticado
        if (auth()->check() && auth()->user()->tenant_id) {
            return auth()->user()->tenant_id;
        }
        
        return null;
    }
}
