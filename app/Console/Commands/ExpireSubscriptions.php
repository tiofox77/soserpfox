<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar e expirar automaticamente subscriptions vencidas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando subscriptions vencidas...');
        
        // Buscar subscriptions com período expirado mas ainda com status 'active' ou 'trial'
        $expiredSubscriptions = Subscription::whereIn('status', ['active', 'trial'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->get();
        
        if ($expiredSubscriptions->isEmpty()) {
            $this->info('✅ Nenhuma subscription vencida encontrada.');
            return 0;
        }
        
        $this->info("📋 Encontradas {$expiredSubscriptions->count()} subscription(s) vencida(s):");
        
        $bar = $this->output->createProgressBar($expiredSubscriptions->count());
        $bar->start();
        
        foreach ($expiredSubscriptions as $subscription) {
            $tenant = $subscription->tenant;
            
            $this->newLine();
            $this->warn("⚠️  Expirando subscription:");
            $this->line("   - Tenant: {$tenant->name} (ID: {$tenant->id})");
            $this->line("   - Subscription ID: {$subscription->id}");
            $this->line("   - Status atual: {$subscription->status}");
            $this->line("   - Período terminou: {$subscription->current_period_end->format('d/m/Y H:i')}");
            $this->line("   - Plano: {$subscription->plan->name}");
            
            // Atualizar status para expirado
            $subscription->update([
                'status' => 'expired',
                'ends_at' => $subscription->current_period_end,
            ]);
            
            $this->info("   ✅ Status atualizado para: expired");
            
            // Registrar no log
            \Log::info('Subscription expirada automaticamente', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'current_period_end' => $subscription->current_period_end->toDateTimeString(),
                'expired_at' => now()->toDateTimeString(),
            ]);
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Processo concluído! {$expiredSubscriptions->count()} subscription(s) expirada(s).");
        
        return 0;
    }
}
