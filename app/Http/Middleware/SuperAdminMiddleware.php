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

        // Super Admin DA PLATAFORMA. NÃO usar hasRole('Super Admin'): esse papel
        // é por empresa (Spatie teams) e todos os donos de tenant o têm — davam
        // entrada no painel global (todas as empresas, chave privada SAFT,
        // script runner…).
        $user = auth()->user();

        if (!$user->isPlatformSuperAdmin()) {
            abort(403, 'Acesso negado. Apenas Super Administradores podem acessar esta área.');
        }

        return $next($request);
    }
}
