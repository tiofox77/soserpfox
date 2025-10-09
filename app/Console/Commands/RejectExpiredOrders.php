<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;

class RejectExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:reject-expired 
                            {--days=7 : Número de dias para considerar pedido expirado}
                            {--dry-run : Apenas simular, não executar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rejeita automaticamente pedidos pendentes há mais de X dias';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("🔍 Buscando pedidos pendentes há mais de {$days} dias...");
        
        // Buscar pedidos pendentes antigos
        $expiredOrders = Order::where('status', 'pending')
            ->where('created_at', '<=', Carbon::now()->subDays($days))
            ->with(['tenant', 'user', 'plan'])
            ->get();
        
        if ($expiredOrders->isEmpty()) {
            $this->info('✅ Nenhum pedido expirado encontrado.');
            return 0;
        }
        
        $this->info("📋 Encontrados {$expiredOrders->count()} pedidos para rejeitar:");
        $this->newLine();
        
        $table = [];
        foreach ($expiredOrders as $order) {
            $daysOld = Carbon::parse($order->created_at)->diffInDays(now());
            
            $table[] = [
                'ID' => $order->id,
                'Tenant' => $order->tenant->name ?? 'N/A',
                'Plano' => $order->plan->name ?? 'N/A',
                'Valor' => 'R$ ' . number_format($order->amount, 2, ',', '.'),
                'Criado há' => "{$daysOld} dias",
                'Data' => $order->created_at->format('d/m/Y H:i'),
            ];
        }
        
        $this->table(
            ['ID', 'Tenant', 'Plano', 'Valor', 'Criado há', 'Data'],
            $table
        );
        
        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN ativo. Nenhum pedido será rejeitado.');
            return 0;
        }
        
        // Confirmar ação
        if (!$this->confirm('Deseja rejeitar estes pedidos?', true)) {
            $this->info('❌ Operação cancelada.');
            return 0;
        }
        
        $this->newLine();
        $this->info('🔄 Rejeitando pedidos...');
        $this->newLine();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($expiredOrders as $order) {
            try {
                $reason = "Pedido pendente há mais de {$days} dias sem confirmação de pagamento.";
                
                $this->line("Rejeitando pedido #{$order->id} ({$order->tenant->name})...");
                
                // Rejeitar pedido (Observer enviará email automaticamente)
                $order->reject($reason, 1); // 1 = Sistema/Auto
                
                $successCount++;
                $this->info("  ✅ Rejeitado com sucesso");
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ❌ Erro: {$e->getMessage()}");
                \Log::error('Erro ao rejeitar pedido expirado', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
        
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("✅ Processamento concluído!");
        $this->info("   • Rejeitados com sucesso: {$successCount}");
        if ($errorCount > 0) {
            $this->error("   • Erros: {$errorCount}");
        }
        $this->info("═══════════════════════════════════════════════════════");
        
        return 0;
    }
}
