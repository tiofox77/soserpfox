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
        // Ignorar para usuários não autenticados
        if (!auth()->check()) {
            return $next($request);
        }
        
        $user = auth()->user();
        
        // Super Admin tem acesso total
        if ($user->is_super_admin) {
            return $next($request);
        }
        
        // Rotas que devem ser sempre acessíveis
        $allowedRoutes = [
            'logout',
            'my-account',
            'register',
            'login',
        ];
        
        foreach ($allowedRoutes as $route) {
            if ($request->is($route) || $request->is($route . '/*')) {
                return $next($request);
            }
        }
        
        // Verificar tenant ativo
        $tenant = $user->activeTenant();
        
        if (!$tenant) {
            return redirect()->route('my-account')
                ->with('error', 'Você não possui uma empresa ativa. Configure sua conta primeiro.');
        }
        
        // Verificar subscription ativa
        $subscription = $tenant->activeSubscription;
        
        if (!$subscription) {
            \Log::warning('Acesso bloqueado - Sem subscription', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'route' => $request->path()
            ]);
            
            return redirect()->route('my-account')
                ->with('error', 'Sua empresa não possui um plano ativo. Por favor, renove sua subscription.');
        }
        
        // Verificar se subscription expirou
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            \Log::warning('Acesso bloqueado - Subscription expirada', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'ends_at' => $subscription->ends_at->toDateTimeString(),
                'route' => $request->path()
            ]);
            
            return redirect()->route('my-account')
                ->with('error', 'Sua subscription expirou em ' . $subscription->ends_at->format('d/m/Y') . '. Renove para continuar acessando o sistema.');
        }
        
        // Verificar status da subscription
        if (!in_array($subscription->status, ['active', 'trial'])) {
            \Log::warning('Acesso bloqueado - Subscription inativa', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'route' => $request->path()
            ]);
            
            $statusMessages = [
                'suspended' => 'Sua subscription foi suspensa. Entre em contato com o suporte.',
                'cancelled' => 'Sua subscription foi cancelada. Renove para continuar acessando.',
                'pending' => 'Sua subscription está pendente de aprovação. Aguarde a confirmação.',
            ];
            
            $message = $statusMessages[$subscription->status] ?? 'Sua subscription não está ativa.';
            
            return redirect()->route('my-account')
                ->with('error', $message);
        }
        
        // Aviso se estiver próximo de expirar (7 dias)
        if ($subscription->ends_at && $subscription->ends_at->diffInDays(now()) <= 7 && $subscription->ends_at->isFuture()) {
            $daysRemaining = $subscription->ends_at->diffInDays(now());
            session()->flash('warning', "Sua subscription expira em {$daysRemaining} dia(s). Renove para evitar interrupções.");
        }
        
        return $next($request);
    }
}
