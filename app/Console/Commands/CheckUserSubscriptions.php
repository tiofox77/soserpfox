<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUserSubscriptions extends Command
{
    protected $signature = 'user:subscriptions {email}';
    protected $description = 'Verificar todas as subscriptions de um usuário';

    public function handle()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("❌ Usuário não encontrado: {$email}");
            return 1;
        }
        
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("👤 Usuário: {$user->name}");
        $this->info("📧 Email: {$user->email}");
        $this->info("🆔 ID: {$user->id}");
        $this->line("");
        
        // Buscar tenants do usuário
        $tenants = $user->tenants;
        
        if ($tenants->isEmpty()) {
            $this->warn("⚠️  Usuário não possui tenants vinculados");
            return 0;
        }
        
        $this->info("🏢 Tenants: {$tenants->count()}");
        $this->line("");
        
        $totalSubscriptions = 0;
        
        foreach ($tenants as $tenant) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🏢 Tenant: {$tenant->name} (ID: {$tenant->id})");
            $this->info("📍 NIF: {$tenant->nif}");
            $this->line("");
            
            $subscriptions = $tenant->subscriptions;
            
            if ($subscriptions->isEmpty()) {
                $this->warn("   ⚠️  Sem subscriptions");
                continue;
            }
            
            $this->info("📦 Subscriptions: {$subscriptions->count()}");
            $this->line("");
            
            $totalSubscriptions += $subscriptions->count();
            
            foreach ($subscriptions as $subscription) {
                $statusIcon = match($subscription->status) {
                    'active' => '✅',
                    'trial' => '🔵',
                    'expired' => '❌',
                    'cancelled' => '🚫',
                    'suspended' => '⏸️',
                    'pending' => '⏳',
                    default => '❓',
                };
                
                $this->line("   ┌─ ID: {$subscription->id}");
                $this->line("   ├─ Plano: {$subscription->plan->name}");
                $this->line("   ├─ Status: {$statusIcon} {$subscription->status}");
                $this->line("   ├─ Ciclo: {$subscription->billing_cycle}");
                $this->line("   ├─ Valor: " . number_format($subscription->amount, 2) . " Kz");
                $this->line("   ├─ Início: " . ($subscription->current_period_start?->format('d/m/Y H:i') ?? 'N/A'));
                $this->line("   ├─ Fim: " . ($subscription->current_period_end?->format('d/m/Y H:i') ?? 'N/A'));
                
                if ($subscription->current_period_end) {
                    $isPast = $subscription->current_period_end->isPast();
                    $diff = $subscription->current_period_end->diffForHumans();
                    
                    if ($isPast && in_array($subscription->status, ['active', 'trial'])) {
                        $this->line("   └─ ⚠️  EXPIRADA mas status ainda {$subscription->status}! ({$diff})");
                    } elseif ($isPast) {
                        $this->line("   └─ Expirou {$diff}");
                    } else {
                        $this->line("   └─ Expira {$diff}");
                    }
                } else {
                    $this->line("   └─ Sem data de término");
                }
                
                $this->line("");
            }
        }
        
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 RESUMO:");
        $this->info("   Total de Tenants: {$tenants->count()}");
        $this->info("   Total de Subscriptions: {$totalSubscriptions}");
        $this->line("");
        
        return 0;
    }
}
