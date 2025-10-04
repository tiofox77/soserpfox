<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantModule
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $moduleSlug
     */
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $user = auth()->user();

        // Super admin tem acesso a todos os módulos
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            abort(403, 'Nenhuma organização identificada.');
        }

        // Verificar se o tenant tem o módulo ativo
        if (!$tenant->hasModule($moduleSlug)) {
            abort(403, 'Este módulo não está disponível para sua organização.');
        }

        return $next($request);
    }
}
