<?php

namespace App\Http\Middleware;

use App\Services\Campanha\AtribuicaoDeCampanha;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captura a origem da campanha (e o módulo) na ATERRAGEM.
 *
 * Aplica-se às páginas públicas por onde os anúncios entram — a inicial, a
 * lista de módulos, cada página de módulo e o próprio registo. Sem sessão
 * aberta ainda (visita anónima), guarda na sessão da visita; respeita o
 * consentimento a jusante (o que é pessoal só se usa com consentimento, no
 * momento de gravar a conversão), e nunca escreve nada no URL.
 *
 * Só age em GET — um POST não é uma aterragem.
 */
class CapturarCampanha
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession()) {
            AtribuicaoDeCampanha::capturar($request, $this->moduloDaRota($request));
        }

        return $next($request);
    }

    /** O slug do módulo quando a rota é `/modulos/{slug}`; senão null. */
    private function moduloDaRota(Request $request): ?string
    {
        $rota = $request->route();

        if ($rota && $rota->getName() === 'modules.show') {
            $slug = $rota->parameter('slug');

            return is_string($slug) ? $slug : null;
        }

        return null;
    }
}
