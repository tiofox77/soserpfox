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

        // Sem auth: o middleware 'auth' lida com isso; aqui apenas deixamos passar
        if (!$user) {
            return $next($request);
        }

        // Super admin tem acesso a todos os módulos
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        // Resolver tenant: primeiro via attributes (preenchido por IdentifyTenant),
        // fallback para activeTenant() do utilizador. O middleware IdentifyTenant
        // está registado como global e corre antes do StartSession do grupo web,
        // portanto pode não ter ainda a sessão disponível para identificar o user.
        $tenant = $request->attributes->get('tenant');
        if (!$tenant) {
            $tenant = $user->activeTenant();
            if ($tenant) {
                $request->attributes->set('tenant', $tenant);
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId($tenant->id);
                }
            }
        }

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
