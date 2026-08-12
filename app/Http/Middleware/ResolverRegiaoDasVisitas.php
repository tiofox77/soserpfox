<?php

namespace App\Http\Middleware;

use App\Services\Analytics\Regiao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descobre de onde vieram as visitas, aproveitando o tráfego.
 *
 * Mesma ideia do despacho das submissões à AGT, e pelo mesmo motivo: não há
 * worker de fila neste alojamento. O trabalho anda em pequenas doses, à boleia
 * de quem está a usar o sistema.
 *
 * Três cuidados, porque isto corre em pedidos de pessoas a trabalhar:
 *
 *   · em `terminate`, DEPOIS de a resposta seguir para o browser — ninguém
 *     espera por uma chamada de rede a um serviço de terceiros;
 *   · com uma tranca de tempo (Cache::add é atómico), no máximo uma vez a cada
 *     cinco minutos e nunca duas em simultâneo;
 *   · em silêncio absoluto. Falhar a descobrir um país não é motivo para
 *     estragar uma página a ninguém.
 */
class ResolverRegiaoDasVisitas
{
    /** Intervalo mínimo entre passagens. */
    private const INTERVALO_SEGUNDOS = 300;

    /** Poucos de cada vez: o serviço gratuito limita os pedidos por minuto. */
    private const POR_PASSAGEM = 15;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS') || $request->is('maintenance/*') || $request->is('api/*')) {
            return;
        }

        if (!Regiao::activo()) {
            return;
        }

        // Cache::add só devolve verdadeiro a QUEM criar a chave: é o que
        // impede dois pedidos simultâneos de irem ambos à rede.
        if (!Cache::add('analytics:regiao:tranca', 1, self::INTERVALO_SEGUNDOS)) {
            return;
        }

        try {
            Regiao::resolverPendentes(self::POR_PASSAGEM);
        } catch (\Throwable $e) {
            Log::debug('Resolução de região falhou', ['erro' => $e->getMessage()]);
        }
    }
}
