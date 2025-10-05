<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Verificar se é Super Admin (flag ou role do Spatie)
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin() || $user->hasRole('Super Admin');
        
        if (!$isSuperAdmin) {
            abort(403, 'Acesso negado. Apenas Super Administradores podem acessar esta área.');
        }

        return $next($request);
    }
}
