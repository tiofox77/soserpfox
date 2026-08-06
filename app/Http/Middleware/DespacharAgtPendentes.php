<?php

namespace App\Http\Middleware;

use App\Services\AGT\DespachoPendentes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Faz andar as submissões AGT aproveitando as visitas ao sistema.
 *
 * Sem worker de fila, nada disto acontecia sozinho: 165 tarefas paradas e 160
 * documentos presos em "Enviada" sem se saber o desfecho. Em vez de exigir um
 * processo permanente que este alojamento não tem, o trabalho anda com o
 * tráfego — qualquer utilizador a abrir uma página faz uma pequena dose.
 *
 * Três cuidados, porque isto corre em pedidos de pessoas a trabalhar:
 *
 *   · em `terminate`, que corre DEPOIS de a resposta seguir para o browser.
 *     Ninguém espera pela AGT.
 *   · com uma tranca de tempo (Cache::add é atómico): no máximo uma vez por
 *     minuto e nunca dois em simultâneo, por muitos utilizadores que haja.
 *   · só a empresa de quem está a navegar. Tratar as outras neste pedido era
 *     trabalho de um contribuinte a correr no contexto de outro — a mesma
 *     mistura que já deu uma fuga entre empresas neste sistema.
 */
class DespacharAgtPendentes
{
    /** Intervalo mínimo entre despachos, por empresa. */
    private const INTERVALO_SEGUNDOS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Pedidos que não são páginas de trabalho não têm de puxar nada.
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*')) {
            return;
        }

        $tenantId = (int) (activeTenantId() ?: 0);

        if (!$tenantId || !auth()->check()) {
            return;
        }

        // Tranca e intervalo numa só operação atómica: quem conseguir escrever
        // a chave é quem despacha; os outros seguem sem fazer nada.
        if (!Cache::add("agt:despacho:{$tenantId}", 1, self::INTERVALO_SEGUNDOS)) {
            return;
        }

        try {
            if (!DespachoPendentes::temTrabalho($tenantId)) {
                return;
            }

            $feito = (new DespachoPendentes())->correr($tenantId);

            if ($feito['enviados'] || $feito['consultados']) {
                Log::info('AGT: despacho pelo tráfego', [
                    'tenant_id'   => $tenantId,
                    'enviados'    => $feito['enviados'],
                    'consultados' => $feito['consultados'],
                ]);
            }
        } catch (\Throwable $e) {
            // A resposta já foi entregue: isto nunca pode afectar quem navegou.
            Log::error('AGT: despacho falhou', [
                'tenant_id' => $tenantId,
                'erro'      => $e->getMessage(),
            ]);
        }
    }
}
