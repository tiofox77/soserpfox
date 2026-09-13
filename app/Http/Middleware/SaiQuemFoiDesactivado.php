<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DESACTIVAR UM UTILIZADOR TIRA-O DO SISTEMA — já, e não no próximo login.
 *
 * O `is_active` da conta não era verificado em lado nenhum da autenticação:
 * quem era desactivado entrava na mesma pelo /login, pela app móvel, e a
 * sessão aberta continuava a funcionar (auditoria de segurança de 2026-09-13).
 * A cada pedido com sessão, uma conta desactivada é terminada.
 */
class SaiQuemFoiDesactivado
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = Auth::user();

        // Só um `false` gravado conta: um modelo acabado de criar ainda não trouxe o valor da base.
        if ($u && $u->getAttribute('is_active') !== null && ! $u->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => __('A sua conta está desactivada.')], 401);
            }

            return redirect()->route('login')->withErrors(['email' => __('A sua conta está desactivada.')]);
        }

        return $next($request);
    }
}
