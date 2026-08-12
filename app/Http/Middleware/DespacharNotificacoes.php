<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Faz sair as notificações agendadas aproveitando quem está a usar o sistema.
 *
 * Nem fila nem worker — o mesmo desenho já usado para as submissões à AGT e
 * para a região dos visitantes, e pela mesma razão: este alojamento não tem
 * processo permanente. O comando `notifications:send-scheduled` existia e
 * dependia de um `schedule:run` que não se sabe se alguma vez corre; os
 * modelos ficavam marcados como activos no ecrã e não saía notificação
 * nenhuma, sem erro em lado nenhum.
 *
 * O QUE TORNA ISTO SEGURO
 * -----------------------
 * O envio passou a ter memória (`notification_sends`, com índice único por
 * modelo, registo, canal, destinatário e DIA). Sem isso, disparar mais vezes
 * não seria uma melhoria: era mandar o mesmo aviso a cada janela do dia, e em
 * SMS e WhatsApp isso é dinheiro da empresa a sair.
 *
 * Três cuidados, porque isto corre em pedidos de pessoas a trabalhar:
 *
 *   · em `terminate`, DEPOIS de a resposta seguir para o browser — ninguém
 *     espera por um SMTP nem por uma operadora de SMS;
 *   · com tranca de tempo (Cache::add é atómico): no máximo uma vez a cada dez
 *     minutos POR EMPRESA, e nunca duas em simultâneo;
 *   · só a empresa de quem está a navegar. Tratar as outras neste pedido era
 *     trabalho de uma empresa a correr no contexto de outra — a mesma mistura
 *     que já deu uma fuga entre empresas neste sistema.
 */
class DespacharNotificacoes
{
    /** Intervalo mínimo entre despachos, por empresa. */
    private const INTERVALO_SEGUNDOS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Pedidos que não são páginas de trabalho não têm de puxar nada.
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*') || $request->is('api/*')) {
            return;
        }

        if (!auth()->check()) {
            return;
        }

        try {
            $tenantId = activeTenantId();
        } catch (\Throwable) {
            return;
        }

        if (!$tenantId) {
            return;
        }

        // Sem o módulo activo não há nada a despachar, e perguntá-lo aqui evita
        // uma varredura de modelos a cada dez minutos em empresas que não o usam.
        if (!$this->temModulo($tenantId)) {
            return;
        }

        // Cache::add só devolve verdadeiro a QUEM criar a chave: é o que impede
        // dois pedidos simultâneos de despacharem ambos.
        if (!Cache::add("notificacoes:despacho:{$tenantId}", 1, self::INTERVALO_SEGUNDOS)) {
            return;
        }

        try {
            Artisan::call('notifications:send-scheduled', ['--tenant' => $tenantId]);
        } catch (\Throwable $e) {
            // Uma notificação que falha não pode estragar a página de quem
            // estava a trabalhar quando o despacho pegou boleia do pedido dele.
            Log::warning('Despacho de notificações falhou', [
                'tenant' => $tenantId,
                'erro'   => $e->getMessage(),
            ]);
        }
    }

    /** A empresa tem o módulo de notificações ligado? */
    private function temModulo(int $tenantId): bool
    {
        return Cache::remember("notificacoes:modulo:{$tenantId}", 300, function () use ($tenantId) {
            return \Illuminate\Support\Facades\DB::table('tenant_module')
                ->join('modules', 'modules.id', '=', 'tenant_module.module_id')
                ->where('tenant_module.tenant_id', $tenantId)
                ->where('modules.slug', 'notifications')
                ->exists();
        });
    }
}
