<?php

namespace App\Http\Controllers\Api\Copias;

use Illuminate\Http\Request;

/**
 * AS CÓPIAS DE UMA EMPRESA — só os dados dela, da empresa activa.
 *
 * Quem gere a conta (papéis «Super Admin» ou «Admin» da empresa): repor apaga e
 * substitui os dados de toda a gente que lá trabalha, e descarregar leva tudo
 * — clientes, salários, facturas. Não é coisa de um caixa.
 */
class CopiasDaEmpresaApiController extends CopiasApiController
{
    protected function ambito(Request $request): ?int
    {
        $tenantId = activeTenantId();

        abort_unless($tenantId && $request->user()?->canManageAccount(), 403, __('Só quem gere a conta da empresa trata das cópias de segurança.'));

        return (int) $tenantId;
    }
}
