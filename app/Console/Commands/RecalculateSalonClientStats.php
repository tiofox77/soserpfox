<?php

namespace App\Console\Commands;

use App\Models\Salon\Client;
use Illuminate\Console\Command;

class RecalculateSalonClientStats extends Command
{
    protected $signature = 'salon:recalculate-stats {--tenant= : ID do tenant específico}';
    protected $description = 'Recalcular estatísticas de visitas e gastos de todos os clientes do salão';

    public function handle()
    {
        $tenantId = $this->option('tenant');

        $query = Client::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
            $this->info("Recalculando estatísticas para tenant ID: {$tenantId}");
        } else {
            $this->info("Recalculando estatísticas para TODOS os tenants");
        }

        $clients = $query->get();
        $bar = $this->output->createProgressBar($clients->count());
        $bar->start();

        $vipCount = 0;
        $updatedCount = 0;

        foreach ($clients as $client) {
            $wasVip = $client->is_vip;
            $client->recalculateStats();
            
            if (!$wasVip && $client->is_vip) {
                $vipCount++;
            }
            $updatedCount++;
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ {$updatedCount} clientes atualizados");
        $this->info("👑 {$vipCount} novos clientes VIP");

        // Mostrar resumo
        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total Clientes', $clients->count()],
                ['Clientes VIP', Client::vip()->count()],
                ['Critério VIP (visitas)', Client::VIP_MIN_VISITS . '+ visitas'],
                ['Critério VIP (gastos)', number_format(Client::VIP_MIN_SPENT, 0, ',', '.') . ' Kz+'],
            ]
        );

        return Command::SUCCESS;
    }
}
