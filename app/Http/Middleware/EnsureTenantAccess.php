<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin tem acesso a tudo
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $request->attributes->get('tenant');

        // Verificar se há um tenant identificado
        if (!$tenant) {
            abort(403, 'Nenhuma organização identificada.');
        }

        // Verificar se o usuário pertence ao tenant
        if (!$user->belongsToTenant($tenant->id)) {
            abort(403, 'Você não tem acesso a esta organização.');
        }

        // Verificar se o usuário está ativo no tenant
        $tenantUser = $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->first();

        if (!$tenantUser || !$tenantUser->pivot->is_active) {
            abort(403, 'Sua conta está inativa nesta organização.');
        }

        return $next($request);
    }
}
