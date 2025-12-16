<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('CheckSubscription Middleware EXECUTANDO', [
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'authenticated' => auth()->check(),
        ]);
        
        // Ignorar para usuários não autenticados
        if (!auth()->check()) {
            \Log::info('CheckSubscription: Usuário não autenticado, pulando verificação');
            return $next($request);
        }
        
        $user = auth()->user();
        
        \Log::info('CheckSubscription: Verificando usuário', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_super_admin' => $user->is_super_admin,
        ]);
        
        // Super Admin tem acesso total
        if ($user->is_super_admin) {
            \Log::info('CheckSubscription: Super Admin, acesso liberado');
            return $next($request);
        }
        
        // Rotas que devem ser sempre acessíveis
        $allowedRoutes = [
            'logout',
            'my-account',
            'register',
            'login',
            'subscription-expired',
        ];
        
        foreach ($allowedRoutes as $route) {
            if ($request->is($route) || $request->is($route . '/*')) {
                \Log::info('CheckSubscription: Rota permitida, acesso liberado', ['route' => $route, 'path' => $request->path()]);
                return $next($request);
            }
        }
        
        // Permitir requisições Livewire do componente MyAccount (para renovação de planos)
        if ($request->is('livewire/*')) {
            $referer = $request->headers->get('referer');
            $allowedComponents = ['App\Livewire\MyAccount'];
            
            // Verificar se é requisição Livewire do componente MyAccount
            if ($referer && (str_contains($referer, '/my-account') || str_contains($referer, '/subscription-expired'))) {
                \Log::info('CheckSubscription: Requisição Livewire permitida de página autorizada', [
                    'referer' => $referer,
                    'path' => $request->path()
                ]);
                return $next($request);
            }
            
            // Verificar pelo nome do componente no payload
            if ($request->has('components')) {
                $components = $request->input('components', []);
                foreach ($components as $component) {
                    if (isset($component['snapshot']['memo']['name']) && 
                        in_array($component['snapshot']['memo']['name'], $allowedComponents)) {
                        \Log::info('CheckSubscription: Componente Livewire permitido', [
                            'component' => $component['snapshot']['memo']['name']
                        ]);
                        return $next($request);
                    }
                }
            }
        }
        
        // Verificar tenant ativo
        $tenant = $user->activeTenant();
        
        if (!$tenant) {
            return redirect()->route('my-account')
                ->with('error', 'Você não possui uma empresa ativa. Configure sua conta primeiro.');
        }
        
        // AUTO-EXPIRAÇÃO: Expirar TODAS as subscriptions vencidas primeiro
        $allSubscriptions = $tenant->subscriptions()->get();
        
        foreach ($allSubscriptions as $sub) {
            if ($sub->current_period_end && 
                $sub->current_period_end->isPast() && 
                in_array($sub->status, ['active', 'trial'])) {
                
                \Log::warning('Auto-expirando subscription', [
                    'subscription_id' => $sub->id,
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'status_anterior' => $sub->status,
                    'current_period_end' => $sub->current_period_end->toDateTimeString(),
                    'expired_at' => now()->toDateTimeString(),
                ]);
                
                $sub->update([
                    'status' => 'expired',
                    'ends_at' => $sub->current_period_end,
                ]);
            }
        }
        
        // Agora buscar subscription válida (após expirar as vencidas)
        $subscription = $tenant->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->where(function($query) {
                // Sem data de fim OU período ainda válido
                $query->whereNull('current_period_end')
                      ->orWhere('current_period_end', '>=', now());
            })
            ->latest()
            ->first();
        
        if (!$subscription) {
            \Log::warning('Acesso bloqueado - Sem subscription válida', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'total_subscriptions' => $allSubscriptions->count(),
                'route' => $request->path()
            ]);
            
            // Pegar última subscription para mostrar na página de expiração
            $lastSubscription = $tenant->subscriptions()
                ->with('plan')
                ->latest()
                ->first();
            
            return redirect()->route('subscription.expired')
                ->with('subscription', $lastSubscription);
        }
        
        // Aviso se estiver próximo de expirar (7 dias)
        if ($subscription->current_period_end && $subscription->current_period_end->diffInDays(now()) <= 7 && $subscription->current_period_end->isFuture()) {
            $daysRemaining = $subscription->current_period_end->diffInDays(now());
            session()->flash('warning', "Seu plano expira em {$daysRemaining} dia(s) ({$subscription->current_period_end->format('d/m/Y')}). Renove para evitar interrupções.");
        }
        
        return $next($request);
    }
}
