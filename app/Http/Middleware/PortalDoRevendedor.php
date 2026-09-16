<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A PORTA DO PORTAL DO REVENDEDOR: só o revendedor APROVADO passa.
 *
 * Suspenso ou recusado depois de ter entrado sai no pedido seguinte — a sessão
 * aberta não pode valer mais do que a decisão do super admin.
 */
class PortalDoRevendedor
{
    public function handle(Request $request, Closure $next): Response
    {
        $revendedor = $request->user('revendedor');

        if ($revendedor && ! $revendedor->aprovado()) {
            Auth::guard('revendedor')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $recado = \App\Http\Controllers\Revenda\EntradaDoRevendedorController::porQueNaoEntra($revendedor);

            return $request->expectsJson()
                ? response()->json(['message' => $recado], 403)
                : redirect()->route('revendedor.login')->with('error', $recado);
        }

        return $next($request);
    }
}
