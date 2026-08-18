<?php

namespace App\Http\Middleware;

use App\Support\AgenteAutenticado;
use Closure;
use Illuminate\Http\Request;

/**
 * Um escopo por rota. Quando falta, a resposta DIZ qual faltou — o agente
 * tem de conseguir perceber o que não pode sem andar a tentar às cegas.
 */
class ExigeEscopoDoAgente
{
    public function handle(Request $request, Closure $next, string $escopo)
    {
        $agente = app(AgenteAutenticado::class);

        if (!$agente->pode($escopo)) {
            return response()->json([
                'erro'         => 'escopo_em_falta',
                'mensagem'     => "Esta credencial não tem o escopo '{$escopo}'.",
                'escopo'       => $escopo,
                'escopos_dados' => $agente->escopos(),
            ], 403);
        }

        return $next($request);
    }
}
