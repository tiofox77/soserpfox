<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class ExpireAllExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire-all';
    protected $description = 'Expirar TODAS as subscriptions vencidas no banco';

    public function handle()
    {
        $this->info('🔍 Buscando subscriptions expiradas...');
        
        $expiredSubscriptions = Subscription::whereIn('status', ['active', 'trial'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->get();
        
        if ($expiredSubscriptions->isEmpty()) {
            $this->info('✅ Nenhuma subscription expirada encontrada!');
            return 0;
        }
        
        $this->warn("⚠️  Encontradas {$expiredSubscriptions->count()} subscription(s) expirada(s) com status incorreto:");
        $this->line("");
        
        foreach ($expiredSubscriptions as $subscription) {
            $this->line("   ID {$subscription->id}: {$subscription->plan->name} (Tenant: {$subscription->tenant->name})");
            $this->line("   └─ Status: {$subscription->status} → expired");
        }
        
        $this->line("");
        
        if (!$this->confirm('Deseja expirar todas agora?', true)) {
            $this->info('Cancelado pelo usuário.');
            return 0;
        }
        
        $this->line("");
        $bar = $this->output->createProgressBar($expiredSubscriptions->count());
        $bar->start();
        
        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update([
                'status' => 'expired',
                'ends_at' => $subscription->current_period_end,
            ]);
            
            \Log::warning('Subscription expirada via comando', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'tenant_name' => $subscription->tenant->name,
                'plan_name' => $subscription->plan->name,
                'current_period_end' => $subscription->current_period_end->toDateTimeString(),
                'expired_at' => now()->toDateTimeString(),
            ]);
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$expiredSubscriptions->count()} subscription(s) expirada(s) com sucesso!");
        
        return 0;
    }
}
