<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O SEPARADOR QUE FICOU ABERTO DE ANTES DO REACT.
 *
 * Os ecrãs deixaram de ser Livewire, mas quem tinha uma página aberta antes do
 * deploy continua com o Livewire no browser a perguntar pelo seu componente
 * (o contador da subscrição, por exemplo, pergunta sozinho de tempos a
 * tempos). A classe já não existe: cada pergunta era um 500 no registo e um
 * erro no ecrã de quem nem tinha tocado em nada. Aconteceu onze segundos
 * depois do deploy de 2026-09-13.
 *
 * Responde 409 — o código que o layout antigo já sabia tratar: recarrega a
 * página no mesmo sítio, e a página que vem é a nova.
 */
class SeparadorDeAntesDoReact
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('X-Livewire') || $request->is('livewire/update', 'livewire/message/*')) {
            return response()->json(['message' => __('O sistema foi actualizado. A página vai recarregar.')], 409);
        }

        return $next($request);
    }
}
