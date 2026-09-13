<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica pedidos com Bearer token (app móvel) na guard de sessão, para que
 * o middleware 'auth' e o activeTenantId() funcionem como nas rotas web.
 * No-op quando não há Bearer (PWA/web continua a usar a sessão).
 */
class ResolveApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer) {
            // Autenticacao por TOKEN (app movel)
            $token = ApiToken::where('token', ApiToken::hashToken($bearer))->first();

            /*
             * O TOKEN CADUCA. Nunca caducava e não se revogava ao desactivar a
             * conta: um funcionário despedido continuava com a API no telemóvel
             * (auditoria de segurança de 2026-09-13). Vale 180 dias desde que
             * nasceu, e cai ao fim de 30 dias sem uso. Conta desactivada: o
             * token apaga-se.
             */
            $caducado = $token && ($token->created_at?->lt(now()->subDays(180))
                || ($token->last_used_at ?? $token->created_at)?->lt(now()->subDays(30)));

            if (!$token || !$token->user || $caducado || !$token->user->is_active) {
                if ($token && ($caducado || ($token->user && !$token->user->is_active))) {
                    $token->delete();
                }

                return response()->json(['message' => 'Token inválido ou expirado.'], 401);
            }
            $user = $token->user;
            Auth::login($user);
            $token->forceFill(['last_used_at' => now()])->saveQuietly();

            // Resolver tenant ativo: header X-Tenant-Id (se pertencer) ou default do user
            $tenantId = $request->header('X-Tenant-Id') ?: $user->tenant_id;
            if ($tenantId && $user->tenants()->where('tenants.id', $tenantId)->exists()) {
                session(['active_tenant_id' => (int) $tenantId]);
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId((int) $tenantId);
                }
            }
            return $next($request);
        }

        // Sem token → exigir sessao web (PWA). Este middleware e o UNICO gate
        // (nao usamos o 'auth' nas rotas para evitar reordenacao de prioridade).
        if (Auth::check()) {
            // A sessão de uma conta desactivada termina aqui também.
            if (Auth::user()->getAttribute('is_active') !== null && !Auth::user()->is_active) {
                Auth::guard('web')->logout();

                return response()->json(['message' => __('A sua conta está desactivada.')], 401);
            }

            return $next($request);
        }

        return response()->json(['message' => 'Não autenticado.'], 401);
    }
}
