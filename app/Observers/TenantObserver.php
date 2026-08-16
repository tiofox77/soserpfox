<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\Plataforma\AvisoDeNovaEmpresa;
use Illuminate\Support\Facades\Log;

/**
 * O aviso ao dono da plataforma vive aqui, e não em cada ecrã de registo.
 *
 * Há três vias que criam uma empresa — o registo público (RegisterWizard), a
 * criação pelo super admin (SuperAdmin\Tenants), e o acrescentar de outra
 * empresa na conta (MyAccount) — e mais nenhuma delas avisava ninguém. Repetir
 * o envio nas três dava três sítios para esquecer quando aparecesse a quarta.
 * No observador, qualquer via que grave um Tenant fica coberta.
 */
class TenantObserver
{
    public function created(Tenant $empresa): void
    {
        try {
            app(AvisoDeNovaEmpresa::class)->notificar($empresa);
        } catch (\Throwable $e) {
            // O serviço já se guarda por dentro; isto é a última rede. Uma
            // empresa não pode deixar de se criar por causa de um aviso.
            Log::error('Aviso de empresa nova falhou por completo.', [
                'tenant_id' => $empresa->id,
                'erro'      => $e->getMessage(),
            ]);
        }
    }
}
