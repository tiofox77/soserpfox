<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Super Admin sempre tem acesso
        if (auth()->user()->is_super_admin) {
            return $next($request);
        }

        // Verificar se o utilizador tem a permissão
        if (!auth()->user()->can($permission)) {
            abort(403, 'Você não tem permissão para aceder a esta funcionalidade.');
        }

        return $next($request);
    }
}
