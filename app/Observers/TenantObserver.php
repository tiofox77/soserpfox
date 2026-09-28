<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\Plataforma\AvisoDeNovaEmpresa;
use Illuminate\Support\Facades\Log;

/**
 * O aviso ao dono da plataforma vive aqui, e não em cada ecrã de registo.
 *
 * Há três vias que criam uma empresa — o registo público (RegisterWizard), a
 * criação pelo super admin (Api\Plataforma\EmpresasApiController), e o acrescentar de outra
 * empresa na conta (MyAccount) — e mais nenhuma delas avisava ninguém. Repetir
 * o envio nas três dava três sítios para esquecer quando aparecesse a quarta.
 * No observador, qualquer via que grave um Tenant fica coberta.
 */
class TenantObserver
{
    public function created(Tenant $empresa): void
    {
        /*
         * O AVISO SAI NO FIM DO PEDIDO (ou do comando), e não aqui (28/09/2026).
         *
         * Quando a linha da empresa nasce, a subscrição — o plano escolhido —
         * e a ligação ao revendedor ainda não existem: o registo público cria
         * a subscrição a seguir, e o revendedor liga-se já fora da transacção.
         * O aviso saía sem nenhum dos dois. No fim do pedido está tudo gravado.
         *
         * E se o registo falhar e a transacção desfizer a empresa, não se avisa
         * de uma empresa que não existe.
         */
        $id = $empresa->id;
        $feito = false;

        app()->terminating(function () use ($id, &$feito) {
            // Uma vez só: nos ensaios a aplicação termina a cada pedido.
            if ($feito) {
                return;
            }
            $feito = true;

            try {
                if ($empresa = Tenant::find($id)) {
                    app(AvisoDeNovaEmpresa::class)->notificar($empresa);
                }
            } catch (\Throwable $e) {
                // O serviço já se guarda por dentro; isto é a última rede. Uma
                // empresa não pode deixar de se criar por causa de um aviso.
                Log::error('Aviso de empresa nova falhou por completo.', [
                    'tenant_id' => $id,
                    'erro'      => $e->getMessage(),
                ]);
            }
        });
    }
}
