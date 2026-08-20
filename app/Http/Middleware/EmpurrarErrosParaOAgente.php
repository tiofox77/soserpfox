<?php

namespace App\Http\Middleware;

use App\Services\Agent\NotificarOpenClaw;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Empurra os erros novos para o agente externo, à boleia do tráfego.
 *
 * Mesmo desenho do FacturarRenovacoes e do AvisarSubscricoes, e pela mesma
 * razão: este alojamento não tem processo permanente e o `schedule:run` pode
 * nunca ser chamado. Um alarme pendurado num cron que não existe é um alarme
 * que nunca toca.
 *
 * O AGENTE TAMBÉM PERGUNTA
 * ------------------------
 * Isto é o sentido "empurrar". O outro — `GET status/resumo`, que o agente
 * pede de hora a hora — continua a existir e é a rede de segurança: se o
 * sistema estiver tão parado que não haja tráfego nenhum, também não há erros
 * novos a comunicar; e se o webhook falhar, a pergunta seguinte do agente
 * traz a mesma informação.
 *
 * Nada aqui pode rebentar: corre no `terminate`, depois de a resposta já ter
 * seguido para o browser, e o serviço apanha tudo por dentro.
 */
class EmpurrarErrosParaOAgente
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*')) {
            return;
        }

        // Os pedidos DO PRÓPRIO agente ficam de fora: sem isto, o agente a
        // consultar a API dispararia o empurrão para si mesmo.
        if ($request->is('api/agent/*')) {
            return;
        }

        if (!config('agent.erros.notificar', false)) {
            return;
        }

        $notificador = app(NotificarOpenClaw::class);

        // Cache::add é atómico: dois pedidos em simultâneo, um só empurrão.
        if (!$notificador->podeCorrer()) {
            return;
        }

        // O serviço não deixa sair excepção nenhuma — nem sequer escreve no
        // log quando falha, porque essa escrita criaria um erro novo que
        // voltava a querer ser notificado.
        $notificador->empurrar();
    }
}
