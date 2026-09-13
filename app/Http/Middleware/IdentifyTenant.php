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
        // Só utilizadores das empresas: no portal do cliente o guard do pedido é
        // `client`, e um Client não pertence a empresas desta maneira.
        if (!auth()->check() || !auth()->user() instanceof \App\Models\User) {
            return $next($request);
        }

        $user = auth()->user();
        $tenant = null;

        // Pedidos do Livewire: resolução curta e obrigatória.
        //
        // Isto SALTAVA a identificação por completo. Como este middleware é o
        // único sítio do caminho web que chama setPermissionsTeamId(), uma
        // acção Livewire corria com a equipa de permissões por omissão —
        // users.tenant_id — e não com a empresa activa da sessão. Quem
        // pertencesse a duas empresas e trocasse para a segunda executava lá as
        // acções com as permissões da PRIMEIRA: comprovado a gravar stock numa
        // empresa onde o utilizador não tinha permissão nenhuma. O simétrico
        // também acontecia — 403 indevido a quem só tinha a permissão na
        // empresa activa.
        //
        // O caminho curto não repete a descoberta por subdomínio (numa acção
        // Livewire a empresa já foi escolhida e está na sessão) nem reescreve a
        // sessão. Faz o essencial: confirmar que o utilizador pertence mesmo à
        // empresa da sessão e fixar o contexto.
        if ($request->is('livewire/*') || $request->is('livewire-update')) {
            $tenant = $this->daSessao($user);

            if ($tenant) {
                $this->fixarContexto($request, $tenant);
            }

            return $next($request);
        }

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
        if (!$tenant) {
            $tenant = $this->daSessao($user);
        }

        // Usar o método activeTenant() do usuário
        if (!$tenant) {
            $tenant = $user->activeTenant();
        }

        if ($tenant) {
            session(['active_tenant_id' => $tenant->id]);
            $this->fixarContexto($request, $tenant);
        } else {
            // Usuário não tem acesso a nenhum tenant
            \Log::warning("User {$user->id} ({$user->email}) doesn't have access to any tenant");
        }

        return $next($request);
    }

    /**
     * Empresa da sessão, só se o utilizador lhe pertencer mesmo.
     *
     * A verificação de pertença não é decorativa: o id vem da sessão, e é ela
     * que impede que uma sessão adulterada aponte para uma empresa alheia.
     */
    private function daSessao($user): ?Tenant
    {
        if (!session()->has('active_tenant_id')) {
            return null;
        }

        $tenant = Tenant::where('is_active', true)->find(session('active_tenant_id'));

        return ($tenant && $user->belongsToTenant($tenant->id)) ? $tenant : null;
    }

    /** Contexto do pedido: empresa das queries e equipa das permissões. */
    private function fixarContexto(Request $request, Tenant $tenant): void
    {
        $request->attributes->set('tenant', $tenant);

        config(['app.active_tenant_id' => $tenant->id]);

        setPermissionsTeamId($tenant->id);
    }
}
