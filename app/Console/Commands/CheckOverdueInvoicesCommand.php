<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\InvoiceExpiringNotification;
use Carbon\Carbon;

class CheckOverdueInvoicesCommand extends Command
{
    protected $signature = 'notifications:check-overdue-invoices';
    protected $description = 'Verificar faturas vencidas e expirando, e notificar usuários';

    public function handle()
    {
        $this->info('🔍 Verificando faturas vencidas e expirando...');
        $this->newLine();
        
        $tenants = \App\Models\Tenant::where('is_active', true)->get();
        
        $overdueCount = 0;
        $expiringCount = 0;
        $notificationsSent = 0;
        
        foreach ($tenants as $tenant) {
            $this->line("Processando tenant: {$tenant->name}");
            
            // Buscar faturas (adaptar para o modelo correto)
            $invoiceModel = $this->getInvoiceModel();
            
            if (!$invoiceModel) {
                $this->warn('Modelo de fatura não encontrado. Pulando...');
                continue;
            }
            
            // 1. FATURAS VENCIDAS (não pagas e data de vencimento passou)
            $overdueInvoices = $invoiceModel::where('tenant_id', $tenant->id)
                ->whereIn('status', ['pending', 'sent', 'partial'])
                ->whereDate('due_date', '<', Carbon::today())
                ->get();
            
            if ($overdueInvoices->isNotEmpty()) {
                $this->warn("  ⚠️  {$overdueInvoices->count()} fatura(s) vencida(s)");
                
                // Buscar admins e gerentes do tenant
                $users = User::role(['Admin', 'Gestor'])
                    ->whereHas('tenants', function($q) use ($tenant) {
                        $q->where('tenants.id', $tenant->id);
                    })
                    ->get();
                
                foreach ($overdueInvoices as $invoice) {
                    $daysOverdue = Carbon::parse($invoice->due_date)->diffInDays(Carbon::today());
                    
                    // Notificar apenas em intervalos específicos para evitar spam
                    // 1, 3, 7, 15, 30, 60, 90 dias
                    if ($this->shouldNotify($daysOverdue)) {
                        foreach ($users as $user) {
                            $user->notify(new InvoiceOverdueNotification($invoice, $daysOverdue));
                            $notificationsSent++;
                        }
                        
                        $this->line("    • Fatura #{$invoice->invoice_number} - {$daysOverdue} dias atrasada");
                    }
                }
                
                $overdueCount += $overdueInvoices->count();
            }
            
            // 2. FATURAS EXPIRANDO EM BREVE (próximos 7 dias)
            $expiringInvoices = $invoiceModel::where('tenant_id', $tenant->id)
                ->whereIn('status', ['pending', 'sent', 'partial'])
                ->whereDate('due_date', '>', Carbon::today())
                ->whereDate('due_date', '<=', Carbon::today()->addDays(7))
                ->get();
            
            if ($expiringInvoices->isNotEmpty()) {
                $this->info("  ℹ️  {$expiringInvoices->count()} fatura(s) expirando em breve");
                
                $users = User::role(['Admin', 'Gestor'])
                    ->whereHas('tenants', function($q) use ($tenant) {
                        $q->where('tenants.id', $tenant->id);
                    })
                    ->get();
                
                foreach ($expiringInvoices as $invoice) {
                    $daysUntilDue = Carbon::today()->diffInDays(Carbon::parse($invoice->due_date));
                    
                    // Notificar em 7, 3 e 1 dia antes
                    if (in_array($daysUntilDue, [7, 3, 1])) {
                        foreach ($users as $user) {
                            $user->notify(new InvoiceExpiringNotification($invoice, $daysUntilDue));
                            $notificationsSent++;
                        }
                        
                        $this->line("    • Fatura #{$invoice->invoice_number} - vence em {$daysUntilDue} dia(s)");
                    }
                }
                
                $expiringCount += $expiringInvoices->count();
            }
            
            if ($overdueInvoices->isEmpty() && $expiringInvoices->isEmpty()) {
                $this->line("  ✅ Nenhuma fatura requer atenção");
            }
            
            $this->newLine();
        }
        
        // Resumo
        $this->newLine();
        $this->info('📊 Resumo Final:');
        $this->table(
            ['Categoria', 'Quantidade'],
            [
                ['Faturas Vencidas', $overdueCount],
                ['Faturas Expirando', $expiringCount],
                ['Notificações Enviadas', $notificationsSent],
            ]
        );
        
        \Log::info('✅ Verificação de faturas concluída', [
            'overdue' => $overdueCount,
            'expiring' => $expiringCount,
            'notifications_sent' => $notificationsSent,
        ]);
        
        return 0;
    }
    
    /**
     * Determinar se deve notificar com base nos dias de atraso
     */
    private function shouldNotify($daysOverdue)
    {
        $notificationDays = [1, 3, 7, 15, 30, 60, 90];
        
        return in_array($daysOverdue, $notificationDays);
    }
    
    /**
     * Obter modelo de fatura correto
     */
    private function getInvoiceModel()
    {
        // Tentar diferentes modelos de fatura
        $models = [
            '\App\Models\Invoicing\SalesInvoice',
            '\App\Models\Invoice',
            '\App\Models\Invoicing\Invoice',
        ];
        
        foreach ($models as $model) {
            if (class_exists($model)) {
                return $model;
            }
        }
        
        return null;
    }
}
