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
     * A empresa cujas permissões contam neste momento.
     *
     * Quando ninguém a fixou, recua para a empresa ACTIVA — a da sessão — e só
     * depois para `users.tenant_id`.
     *
     * O recuo directo para `users.tenant_id` que aqui esteve era uma escalada
     * de privilégios silenciosa: um utilizador que pertence a duas empresas e
     * trocou para a segunda passava a executar lá as acções com as permissões
     * da PRIMEIRA, porque `users.tenant_id` continua a apontar para a empresa
     * de origem. Comprovado a gravar stock numa empresa onde o utilizador não
     * tinha permissão nenhuma. O simétrico também acontecia — 403 indevido a
     * quem só tinha a permissão na empresa activa.
     *
     * Um recuo destes nunca deve dar MAIS acesso do que o pedido tem direito.
     */
    public function getPermissionsTeamId(): int|string|null
    {
        if ($this->teamId !== null) {
            return $this->teamId;
        }

        if (!auth()->check()) {
            return null;
        }

        $utilizador = auth()->user();

        // A empresa da sessão só conta se o utilizador lhe pertencer mesmo — é
        // o `activeTenant()` que o confirma, procurando dentro das empresas
        // dele. Uma sessão adulterada não abre porta nenhuma.
        if (method_exists($utilizador, 'activeTenantId')) {
            try {
                $activa = $utilizador->activeTenantId();

                if ($activa) {
                    return $activa;
                }
            } catch (\Throwable) {
                // Fora de um pedido web (consola, fila) não há sessão: segue
                // para a empresa de origem, que é o melhor palpite possível.
            }
        }

        return $utilizador->tenant_id ?: null;
    }
}
