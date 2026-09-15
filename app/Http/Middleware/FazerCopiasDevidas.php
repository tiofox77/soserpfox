<?php

namespace App\Http\Middleware;

use App\Services\Copias\Agendador;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * FAZ AS CÓPIAS DE SEGURANÇA QUE ESTÃO NA HORA — à boleia do tráfego.
 *
 * O mesmo desenho do FacturarRenovacoes e do DespacharNotificacoes: este
 * alojamento pode não ter `schedule:run`, e uma cópia de 6 em 6 horas pendurada
 * num cron que não existe é uma cópia que nunca se faz. Corre em `terminate`,
 * depois de a resposta seguir para o browser; com tranca de cinco minutos entre
 * passagens e uma cópia no máximo por passagem.
 *
 * Qualquer página serve de relógio — não só as de quem tem sessão: numa
 * madrugada sem ninguém a trabalhar, uma visita ao site ainda faz a cópia.
 */
class FazerCopiasDevidas
{
    private const TRANCA = 'copias:agendador';

    private const INTERVALO_SEGUNDOS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*') || $request->is('api/*') || app()->runningUnitTests()) {
            return;
        }

        if (! Cache::add(self::TRANCA, 1, self::INTERVALO_SEGUNDOS)) {
            return;
        }

        try {
            @set_time_limit(0);
            @ignore_user_abort(true);
            app(Agendador::class)->correrUma();
        } catch (\Throwable $e) {
            Log::warning('O agendador das cópias falhou', ['erro' => $e->getMessage()]);
        }
    }
}
