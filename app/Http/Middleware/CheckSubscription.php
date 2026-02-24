<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Subscription;

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
            'subscription-expired',
            'offline',
        ];
        
        foreach ($allowedRoutes as $route) {
            if ($request->is($route) || $request->is($route . '/*')) {
                return $next($request);
            }
        }
        
        // Permitir requisições Livewire do componente MyAccount (para renovação de planos)
        if ($request->is('livewire/*')) {
            $referer = $request->headers->get('referer');
            
            if ($referer && (str_contains($referer, '/my-account') || str_contains($referer, '/subscription-expired'))) {
                return $next($request);
            }
            
            if ($request->has('components')) {
                $components = $request->input('components', []);
                foreach ($components as $component) {
                    if (isset($component['snapshot']['memo']['name']) && 
                        $component['snapshot']['memo']['name'] === 'App\Livewire\MyAccount') {
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
        
        // AUTO-EXPIRAÇÃO: Expirar subscriptions vencidas deste tenant
        $this->autoExpireSubscriptions($tenant);
        
        // Buscar subscription válida do tenant actual
        $subscription = $this->findActiveSubscription($tenant);
        
        // BUG-02 FIX: Se tenant actual não tem subscription, procurar em QUALQUER tenant do user
        if (!$subscription) {
            $subscription = $this->findAndPropagateUserSubscription($user, $tenant);
        }
        
        if (!$subscription) {
            \Log::warning('CheckSubscription: Sem subscription válida', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);
            
            $lastSubscription = $tenant->subscriptions()
                ->with('plan')
                ->latest()
                ->first();
            
            return redirect()->route('subscription.expired')
                ->with('subscription', $lastSubscription);
        }
        
        // Aviso se estiver próximo de expirar (7 dias)
        if ($subscription->current_period_end && $subscription->current_period_end->isFuture()) {
            $daysRemaining = (int) now()->diffInDays($subscription->current_period_end, false);
            if ($daysRemaining >= 0 && $daysRemaining <= 7) {
                session()->flash('warning', "Seu plano expira em {$daysRemaining} dia(s) ({$subscription->current_period_end->format('d/m/Y')}). Renove para evitar interrupções.");
            }
        }
        
        return $next($request);
    }
    
    /**
     * Auto-expirar subscriptions vencidas de um tenant
     */
    protected function autoExpireSubscriptions($tenant): void
    {
        $tenant->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->each(function ($sub) {
                \Log::warning('Auto-expirando subscription', [
                    'subscription_id' => $sub->id,
                    'tenant_id' => $sub->tenant_id,
                ]);
                $sub->update([
                    'status' => 'expired',
                    'ends_at' => $sub->current_period_end,
                ]);
            });
    }
    
    /**
     * Encontrar subscription activa de um tenant
     */
    protected function findActiveSubscription($tenant)
    {
        return $tenant->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->where(function($query) {
                $query->whereNull('current_period_end')
                      ->orWhere('current_period_end', '>=', now());
            })
            ->latest()
            ->first();
    }
    
    /**
     * BUG-02 FIX: Procurar subscription activa em QUALQUER tenant do user
     * e propagar para o tenant actual se encontrar
     */
    protected function findAndPropagateUserSubscription($user, $currentTenant)
    {
        // Procurar em todos os tenants do user
        $userTenants = $user->tenants()->get();
        
        foreach ($userTenants as $otherTenant) {
            if ($otherTenant->id === $currentTenant->id) {
                continue;
            }
            
            // Auto-expirar subscriptions vencidas do outro tenant também
            $this->autoExpireSubscriptions($otherTenant);
            
            $sourceSubscription = $this->findActiveSubscription($otherTenant);
            
            if ($sourceSubscription) {
                // Propagar subscription para o tenant actual
                $newSubscription = $this->propagateSubscription($currentTenant, $sourceSubscription);
                
                if ($newSubscription) {
                    \Log::info('CheckSubscription: Subscription propagada automaticamente', [
                        'user_id' => $user->id,
                        'source_tenant' => $otherTenant->id,
                        'target_tenant' => $currentTenant->id,
                        'plan' => $sourceSubscription->plan->name ?? 'N/A',
                    ]);
                    
                    return $newSubscription;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Propagar subscription de um tenant para outro
     * Também sincroniza módulos do plano
     */
    protected function propagateSubscription($targetTenant, $sourceSubscription)
    {
        try {
            // Verificar se já não existe subscription activa
            $existing = $this->findActiveSubscription($targetTenant);
            if ($existing) {
                return $existing;
            }
            
            // Criar subscription clone com MESMAS datas
            $newSubscription = $targetTenant->subscriptions()->create([
                'plan_id'              => $sourceSubscription->plan_id,
                'status'               => $sourceSubscription->status,
                'billing_cycle'        => $sourceSubscription->billing_cycle,
                'amount'               => $sourceSubscription->amount,
                'current_period_start' => $sourceSubscription->current_period_start,
                'current_period_end'   => $sourceSubscription->current_period_end,
                'ends_at'              => $sourceSubscription->ends_at,
                'trial_ends_at'        => $sourceSubscription->trial_ends_at,
            ]);
            
            // Sincronizar módulos do plano no target tenant
            $plan = $sourceSubscription->plan;
            if ($plan) {
                $moduleIds = $plan->modules()->pluck('modules.id')->toArray();
                if (!empty($moduleIds)) {
                    $syncData = [];
                    foreach ($moduleIds as $moduleId) {
                        $syncData[$moduleId] = [
                            'is_active' => true,
                            'activated_at' => now(),
                        ];
                    }
                    $targetTenant->modules()->syncWithoutDetaching($syncData);
                }
            }
            
            return $newSubscription->load('plan');
            
        } catch (\Exception $e) {
            \Log::error('CheckSubscription: Erro ao propagar subscription', [
                'target_tenant' => $targetTenant->id,
                'source_subscription' => $sourceSubscription->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
