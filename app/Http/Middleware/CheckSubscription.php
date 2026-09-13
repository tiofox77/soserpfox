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

        // Só os utilizadores das empresas têm subscrição. No portal do cliente o
        // `auth:client` põe o guard `client` como o do pedido, e `auth()->user()`
        // devolve um Client — que não tem empresa activa: o activeTenant() mais
        // abaixo rebentava e as páginas do portal davam erro 500.
        if (! $user instanceof \App\Models\User) {
            return $next($request);
        }

        // Super Admin tem acesso total
        if ($user->is_super_admin) {
            return $next($request);
        }
        
        /*
         * AS PORTAS QUE NUNCA FECHAM.
         *
         * A área de conta é onde se PAGA. Fechá-la a quem deixou de ter
         * subscrição era trancar o cliente do lado de fora com a chave lá
         * dentro: a página abria e nenhum dos pedidos que a alimentam passava.
         *
         * `api/v1/invoicing/react/conta` é a API dessa página — tem de estar
         * aqui pela mesma razão que a morada dela.
         *
         * `api/v1/casca` é o topo de todas as páginas — a empresa activa, o
         * contador e o sino. Tem de responder na página de conta de quem já não
         * tem subscrição, e a troca de empresa tem de deixar SAIR de uma empresa
         * sem subscrição para outra que a tenha.
         */
        $allowedRoutes = [
            'logout',
            'my-account',
            'api/v1/invoicing/react/conta',
            'api/v1/casca',
            'register',
            'login',
            'subscription-expired',
            'offline',
        ];

        foreach ($allowedRoutes as $route) {
            if ($request->is($route) || $request->is($route.'/*')) {
                return $next($request);
            }
        }

        // Verificar tenant ativo
        $tenant = $user->activeTenant();
        
        if (!$tenant) {
            if ($this->esperaJson($request)) {
                return response()->json([
                    'success' => false,
                    'code'    => 'no_active_tenant',
                    'error'   => 'Não há empresa activa nesta conta.',
                ], 403);
            }

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

            // UMA RECUSA TEM DE SE PARECER COM UMA RECUSA.
            //
            // Para o PWA, um 302 para a página de renovação era o pior dos
            // mundos: o `fetch` do motor SEGUE o redireccionamento, recebe
            // HTML com estado 200, e não tem como distinguir uma empresa que
            // deixou de pagar de uma ligação que caiu. O `ping` chegava a
            // responder OK — o aparelho julgava-se online e continuava a
            // vender, a bater numa porta fechada até ao fim do turno, sem uma
            // linha a explicar ao operador.
            //
            // 402 é o código que existe exactamente para isto, e vem com o
            // motivo em JSON para o motor poder agir: parar a fila e dizer o
            // que se passa, em vez de arquivar tudo como falha de rede.
            if ($this->esperaJson($request)) {
                return response()->json([
                    'success' => false,
                    'code'    => 'subscription_expired',
                    'error'   => 'A subscrição desta empresa expirou. Renove o plano para continuar a emitir documentos.',
                    'renovar' => route('subscription.expired'),
                ], 402);
            }

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
     * Este pedido quer uma resposta que uma máquina saiba ler?
     *
     * O `expectsJson()` do Laravel sozinho não chega: depende de o cliente
     * mandar o cabeçalho certo, e um `fetch` sem `Accept` explícito passa
     * despercebido. O prefixo da API é a garantia — tudo o que vive sob
     * `api/` é consumido por código, nunca por um browser a mostrar uma
     * página, e um 302 para HTML nunca é a resposta certa aí.
     */
    protected function esperaJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
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

            // Respeitar o limite de empresas do plano (o OrderObserver já o faz;
            // aqui não era aplicado, pelo que UM plano cobria empresas sem fim).
            $plan = $sourceSubscription->plan;
            $maxCompanies = (int) ($plan->max_companies ?? 1);

            $cobertas = 0;
            foreach (auth()->user()?->tenants()->get() ?? collect() as $t) {
                if ($this->findActiveSubscription($t)) {
                    $cobertas++;
                }
            }

            if ($maxCompanies > 0 && $cobertas >= $maxCompanies) {
                \Log::warning('CheckSubscription: limite de empresas do plano atingido — propagação recusada', [
                    'target_tenant' => $targetTenant->id,
                    'plan'          => $plan->name ?? $sourceSubscription->plan_id,
                    'max_companies' => $maxCompanies,
                    'cobertas'      => $cobertas,
                ]);
                return null;
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
            
            // Sincronizar módulos do plano no target tenant — via serviço, para
            // levar dependências (Faturação ⇒ Tesouraria), pré-requisitos
            // (métodos de pagamento, impostos, armazém) e permissões.
            if ($plan) {
                $slugs = $plan->modules()->pluck('modules.slug')->toArray();
                if (!empty($slugs)) {
                    $sync = new \App\Services\Tenant\TenantModuleSyncService();
                    foreach ($slugs as $slug) {
                        $sync->activateModule($targetTenant, $slug);
                    }
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
