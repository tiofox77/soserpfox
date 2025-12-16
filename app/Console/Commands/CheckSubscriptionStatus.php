<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class CheckSubscriptionStatus extends Command
{
    protected $signature = 'subscription:check {tenant_id?}';
    protected $description = 'Verificar status detalhado de uma subscription';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        
        if ($tenantId) {
            $subscriptions = Subscription::where('tenant_id', $tenantId)->get();
        } else {
            $subscriptions = Subscription::all();
        }
        
        if ($subscriptions->isEmpty()) {
            $this->error('Nenhuma subscription encontrada.');
            return 1;
        }
        
        foreach ($subscriptions as $subscription) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📋 Subscription ID: {$subscription->id}");
            $this->info("🏢 Tenant: {$subscription->tenant->name} (ID: {$subscription->tenant_id})");
            $this->info("📦 Plano: {$subscription->plan->name}");
            $this->line("");
            
            $this->info("📊 Status Atual:");
            $this->line("   Status: " . $subscription->status);
            $this->line("   Billing Cycle: " . $subscription->billing_cycle);
            $this->line("   Amount: " . number_format($subscription->amount, 2) . " Kz");
            $this->line("");
            
            $this->info("📅 Datas:");
            $this->line("   Início: " . ($subscription->current_period_start?->format('d/m/Y H:i:s') ?? 'N/A'));
            $this->line("   Fim: " . ($subscription->current_period_end?->format('d/m/Y H:i:s') ?? 'N/A'));
            $this->line("   Agora: " . now()->format('d/m/Y H:i:s'));
            $this->line("");
            
            if ($subscription->current_period_end) {
                $isPast = $subscription->current_period_end->isPast();
                $diff = $subscription->current_period_end->diffForHumans();
                
                $this->info("🔍 Análise:");
                $this->line("   Expirou? " . ($isPast ? '✅ SIM' : '❌ NÃO'));
                $this->line("   Diferença: {$diff}");
                $this->line("   Timestamp Fim: " . $subscription->current_period_end->timestamp);
                $this->line("   Timestamp Agora: " . now()->timestamp);
                $this->line("   Diferença (segundos): " . ($subscription->current_period_end->timestamp - now()->timestamp));
                $this->line("");
                
                // Verificar se deve expirar
                $shouldExpire = $isPast && in_array($subscription->status, ['active', 'trial']);
                
                if ($shouldExpire) {
                    $this->warn("⚠️  DEVERIA EXPIRAR!");
                    
                    if ($this->confirm('Deseja expirar agora?', true)) {
                        $subscription->update([
                            'status' => 'expired',
                            'ends_at' => $subscription->current_period_end,
                        ]);
                        
                        $this->info("✅ Subscription expirada com sucesso!");
                    }
                } else {
                    $this->info("✅ Subscription válida");
                }
            } else {
                $this->warn("⚠️  Sem data de término definida");
            }
            
            $this->line("");
        }
        
        return 0;
    }
}
