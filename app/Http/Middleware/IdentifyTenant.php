<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Debug: Log da requisição
        \Log::info('IdentifyTenant Middleware', [
            'url' => $request->url(),
            'path' => $request->path(),
            'method' => $request->method(),
            'is_livewire' => $request->is('livewire/*'),
        ]);
        
        // Ignorar rotas do Livewire e assets
        if ($request->is('livewire/*') || $request->is('livewire-update')) {
            \Log::info('IdentifyTenant: Ignorando rota Livewire');
            return $next($request);
        }
        
        if (!auth()->check()) {
            \Log::info('IdentifyTenant: Usuário não autenticado');
            return $next($request);
        }
        
        $user = auth()->user();
        $tenant = null;

        // Tentar identificar tenant por subdomínio
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        if ($subdomain && $subdomain !== 'www' && $subdomain !== config('app.domain')) {
            $tenant = Tenant::where('slug', $subdomain)
                ->orWhere('domain', $host)
                ->where('is_active', true)
                ->first();
                
            // Verificar se usuário tem acesso a esse tenant
            if ($tenant && !$user->belongsToTenant($tenant->id)) {
                $tenant = null;
            }
        }

        // Tentar identificar tenant por sessão (active_tenant_id)
        if (!$tenant && session()->has('active_tenant_id')) {
            $tenant = Tenant::where('is_active', true)
                ->find(session('active_tenant_id'));
                
            // Verificar se usuário tem acesso a esse tenant
            if ($tenant && !$user->belongsToTenant($tenant->id)) {
                $tenant = null;
            }
        }

        // Usar o método activeTenant() do usuário
        if (!$tenant) {
            $tenant = $user->activeTenant();
        }

        // Armazenar tenant na sessão e no request
        if ($tenant) {
            session(['active_tenant_id' => $tenant->id]);
            $request->attributes->set('tenant', $tenant);
            
            // Configurar contexto global para queries
            config(['app.active_tenant_id' => $tenant->id]);
            
            // Configurar team_id para Spatie Permission
            setPermissionsTeamId($tenant->id);
        } else {
            // Usuário não tem acesso a nenhum tenant
            \Log::warning("User {$user->id} ({$user->email}) doesn't have access to any tenant");
        }

        return $next($request);
    }
}
