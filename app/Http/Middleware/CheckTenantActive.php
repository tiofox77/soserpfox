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
        // A API do agente externo fica de fora.
        //
        // Este middleware e GLOBAL, logo apanha-a tambem — e ali fazia duas
        // coisas erradas: respondia a uma chamada maquina-a-maquina com um
        // redirect para uma pagina HTML, e mexia na sessao (session() e
        // auth()->logout()) num pedido que foi montado de proposito SEM
        // sessao. O agente que suspendesse uma empresa levava 302 na
        // chamada seguinte e nao percebia porque.
        //
        // O acesso do agente ja e decidido pelo token e pelos escopos; que a
        // empresa esteja suspensa e informacao que ele PRECISA de poder ler.
        if ($request->is('api/agent/*')) {
            return $next($request);
        }

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
