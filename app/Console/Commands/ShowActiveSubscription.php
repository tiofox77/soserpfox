<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ShowActiveSubscription extends Command
{
    protected $signature = 'user:active-subscription {email}';
    protected $description = 'Mostrar qual é a subscription ativa do usuário';

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
        $this->line("");
        
        $tenants = $user->tenants;
        
        foreach ($tenants as $tenant) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🏢 Tenant: {$tenant->name} (ID: {$tenant->id})");
            $this->line("");
            
            // Todas as subscriptions do tenant
            $allSubscriptions = $tenant->subscriptions()->orderBy('id', 'desc')->get();
            
            if ($allSubscriptions->isEmpty()) {
                $this->warn("   ⚠️  Sem subscriptions");
                continue;
            }
            
            $this->info("📦 Total de subscriptions: {$allSubscriptions->count()}");
            $this->line("");
            
            // Listar TODAS
            $this->info("📋 TODAS AS SUBSCRIPTIONS (da mais recente para mais antiga):");
            foreach ($allSubscriptions as $index => $sub) {
                $number = $index + 1;
                $statusIcon = match($sub->status) {
                    'active' => '✅',
                    'trial' => '🔵',
                    'expired' => '❌',
                    'cancelled' => '🚫',
                    'suspended' => '⏸️',
                    'pending' => '⏳',
                    default => '❓',
                };
                
                $isExpired = $sub->current_period_end && $sub->current_period_end->isPast();
                $expiredWarning = ($isExpired && in_array($sub->status, ['active', 'trial'])) ? ' ⚠️ EXPIRADA!' : '';
                
                $this->line("   {$number}. ID {$sub->id}: {$statusIcon} {$sub->status} - {$sub->plan->name} ({$sub->billing_cycle}){$expiredWarning}");
                $this->line("      Criada: {$sub->created_at->format('d/m/Y H:i:s')}");
                $this->line("      Período: " . ($sub->current_period_start?->format('d/m/Y') ?? 'N/A') . " até " . ($sub->current_period_end?->format('d/m/Y') ?? 'N/A'));
            }
            
            $this->line("");
            $this->info("🎯 ÚLTIMA SUBSCRIPTION CRIADA:");
            $lastSubscription = $allSubscriptions->first(); // Mais recente
            
            $statusIcon = match($lastSubscription->status) {
                'active' => '✅',
                'trial' => '🔵',
                'expired' => '❌',
                'cancelled' => '🚫',
                'suspended' => '⏸️',
                'pending' => '⏳',
                default => '❓',
            };
            
            $this->line("   ┌─ ID: {$lastSubscription->id}");
            $this->line("   ├─ Status: {$statusIcon} {$lastSubscription->status}");
            $this->line("   ├─ Plano: {$lastSubscription->plan->name}");
            $this->line("   ├─ Ciclo: {$lastSubscription->billing_cycle}");
            $this->line("   ├─ Valor: " . number_format($lastSubscription->amount, 2) . " Kz");
            $this->line("   ├─ Criada em: {$lastSubscription->created_at->format('d/m/Y H:i:s')}");
            $this->line("   ├─ Início: " . ($lastSubscription->current_period_start?->format('d/m/Y H:i') ?? 'N/A'));
            $this->line("   └─ Fim: " . ($lastSubscription->current_period_end?->format('d/m/Y H:i') ?? 'N/A'));
            
            if ($lastSubscription->current_period_end) {
                $isPast = $lastSubscription->current_period_end->isPast();
                $diff = $lastSubscription->current_period_end->diffForHumans();
                
                $this->line("");
                if ($isPast && in_array($lastSubscription->status, ['active', 'trial'])) {
                    $this->warn("   ⚠️  ESTA SUBSCRIPTION ESTÁ EXPIRADA mas status é '{$lastSubscription->status}'!");
                    $this->warn("   ⚠️  Expirou {$diff}");
                    $this->warn("   ⚠️  DEVERIA SER EXPIRADA AUTOMATICAMENTE!");
                } elseif ($isPast) {
                    $this->info("   ℹ️  Expirou {$diff}");
                } else {
                    $this->info("   ✅ Válida - Expira {$diff}");
                }
            }
            
            $this->line("");
            
            // Mostrar o que o activeSubscription retornaria
            $activeSubscription = $tenant->activeSubscription;
            
            if ($activeSubscription) {
                $this->info("🔍 O QUE activeSubscription() RETORNA:");
                $this->line("   ID {$activeSubscription->id}: {$activeSubscription->status} - {$activeSubscription->plan->name}");
                $this->line("   (Esta é a que o sistema está usando agora)");
            } else {
                $this->warn("🔍 activeSubscription() retorna NULL");
                $this->warn("   (Sistema não encontra subscription válida)");
            }
            
            $this->line("");
        }
        
        return 0;
    }
}
