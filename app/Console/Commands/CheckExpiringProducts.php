<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoicing\ProductBatch;
use App\Models\Tenant;
use App\Notifications\ProductExpiringNotification;
use App\Notifications\ProductExpiredNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class CheckExpiringProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:check-expiry 
                            {--tenant= : ID do tenant específico}
                            {--notify : Enviar notificações}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica produtos próximos da validade e envia alertas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando produtos próximos da validade...');
        
        // Determinar tenants
        $tenants = $this->option('tenant') 
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::where('is_active', true)->get();
        
        $totalExpiringSoon = 0;
        $totalExpired = 0;
        
        foreach ($tenants as $tenant) {
            $this->info("\n📊 Tenant: {$tenant->name}");
            
            // Produtos expirando em breve
            $expiringSoon = ProductBatch::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->whereNotNull('expiry_date')
                ->where(function($query) {
                    $query->whereRaw('DATEDIFF(expiry_date, NOW()) <= alert_days')
                          ->whereDate('expiry_date', '>=', Carbon::now());
                })
                ->with(['product', 'warehouse'])
                ->get();
            
            // Produtos já expirados
            $expired = ProductBatch::where('tenant_id', $tenant->id)
                ->where('quantity_available', '>', 0)
                ->whereDate('expiry_date', '<', Carbon::now())
                ->with(['product', 'warehouse'])
                ->get();
            
            $totalExpiringSoon += $expiringSoon->count();
            $totalExpired += $expired->count();
            
            // Exibir estatísticas
            $this->line("  🟠 Expirando em breve: {$expiringSoon->count()}");
            $this->line("  🔴 Já expirados: {$expired->count()}");
            
            // Listar produtos
            if ($expiringSoon->count() > 0) {
                $this->warn("\n  ⚠️  Produtos Expirando em Breve:");
                foreach ($expiringSoon as $batch) {
                    $days = $batch->days_until_expiry;
                    $warehouseName = $batch->warehouse ? $batch->warehouse->name : 'N/A';
                    $this->line("    • {$batch->product->name} (Lote: {$batch->batch_number}) - {$days} dias - {$warehouseName}");
                }
            }
            
            if ($expired->count() > 0) {
                $this->error("\n  ❌ Produtos Já Expirados:");
                foreach ($expired as $batch) {
                    $days = abs($batch->days_until_expiry);
                    $warehouseName = $batch->warehouse ? $batch->warehouse->name : 'N/A';
                    $this->line("    • {$batch->product->name} (Lote: {$batch->batch_number}) - há {$days} dias - {$warehouseName}");
                }
            }
            
            // Enviar notificações
            if ($this->option('notify')) {
                $this->sendNotifications($tenant, $expiringSoon, $expired);
            }
            
            // Atualizar status de lotes expirados
            $expired->each(function($batch) {
                if ($batch->status !== 'expired') {
                    $batch->updateStatus();
                }
            });
        }
        
        // Resumo final
        $this->info("\n" . str_repeat('=', 60));
        $this->info("✅ Verificação concluída!");
        $this->info("   Total expirando em breve: {$totalExpiringSoon}");
        $this->info("   Total já expirados: {$totalExpired}");
        
        if ($this->option('notify')) {
            $this->info("   📧 Notificações enviadas");
        } else {
            $this->comment("   💡 Use --notify para enviar notificações");
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Enviar notificações para o tenant
     */
    private function sendNotifications($tenant, $expiringSoon, $expired)
    {
        // Buscar usuários do tenant para notificar
        $users = $tenant->users()->where('is_active', true)->get();
        
        if ($users->isEmpty()) {
            $this->warn("  ⚠️  Nenhum usuário ativo para notificar");
            return;
        }
        
        // Notificar sobre produtos expirando
        if ($expiringSoon->count() > 0) {
            Notification::send($users, new ProductExpiringNotification($expiringSoon, $tenant));
            $this->info("  📧 Notificação de produtos expirando enviada para {$users->count()} usuário(s)");
        }
        
        // Notificar sobre produtos já expirados
        if ($expired->count() > 0) {
            Notification::send($users, new ProductExpiredNotification($expired, $tenant));
            $this->info("  📧 Notificação de produtos expirados enviada para {$users->count()} usuário(s)");
        }
    }
}
