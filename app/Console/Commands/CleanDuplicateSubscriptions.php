<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class CleanDuplicateSubscriptions extends Command
{
    protected $signature = 'subscriptions:clean-duplicates';
    protected $description = 'Remover subscriptions pendentes duplicadas';

    public function handle()
    {
        $this->info('🔍 Buscando subscriptions pendentes duplicadas...');
        
        // Agrupar por tenant_id e plan_id
        $duplicates = Subscription::where('status', 'pending')
            ->get()
            ->groupBy(function ($sub) {
                return $sub->tenant_id . '_' . $sub->plan_id;
            })
            ->filter(function ($group) {
                return $group->count() > 1; // Apenas grupos com mais de 1
            });
        
        if ($duplicates->isEmpty()) {
            $this->info('✅ Nenhuma subscription pendente duplicada encontrada!');
            return 0;
        }
        
        $totalRemoved = 0;
        
        foreach ($duplicates as $key => $group) {
            list($tenantId, $planId) = explode('_', $key);
            
            $tenant = \App\Models\Tenant::find($tenantId);
            $plan = \App\Models\Plan::find($planId);
            
            $this->warn("⚠️  Tenant: {$tenant->name} | Plano: {$plan->name}");
            $this->line("   Total: {$group->count()} subscriptions pendentes");
            
            // Manter apenas a mais recente, deletar as outras
            $latest = $group->sortByDesc('created_at')->first();
            $toDelete = $group->reject(function ($sub) use ($latest) {
                return $sub->id === $latest->id;
            });
            
            foreach ($toDelete as $sub) {
                $this->line("   🗑️  Removendo ID {$sub->id} (criada em {$sub->created_at->format('d/m/Y H:i')})");
                $sub->delete();
                $totalRemoved++;
            }
            
            $this->info("   ✅ Mantida ID {$latest->id} (mais recente: {$latest->created_at->format('d/m/Y H:i')})");
        }
        
        $this->newLine();
        $this->info("✅ {$totalRemoved} subscription(s) pendente(s) duplicada(s) removida(s)!");
        
        return 0;
    }
}
