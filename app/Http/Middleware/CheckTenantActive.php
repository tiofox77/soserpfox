<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class CheckTenantActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pular verificação para super admin
        if (auth()->check() && auth()->user()->is_super_admin) {
            return $next($request);
        }
        
        // Verificar se tem tenant ativo
        $tenantId = activeTenantId();
        
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            
            // Se tenant não existe ou está inativo
            if (!$tenant || !$tenant->is_active) {
                // Salvar informações na sessão
                session([
                    'tenant_deactivated' => true,
                    'tenant_name' => $tenant ? $tenant->name : 'Desconhecido',
                    'deactivation_reason' => $tenant ? $tenant->deactivation_reason : null,
                    'deactivated_at' => $tenant && $tenant->deactivated_at ? $tenant->deactivated_at->format('d/m/Y H:i') : null,
                ]);
                
                // Fazer logout do usuário
                auth()->logout();
                
                return redirect()->route('tenant.deactivated');
            }
        }
        
        return $next($request);
    }
}
