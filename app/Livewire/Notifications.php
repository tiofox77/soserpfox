<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Order;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Notifications extends Component
{
    public $showDropdown = false;
    public $showOnlyUnread = true;
    
    protected $listeners = ['notificationCreated' => '$refresh'];
    
    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
    }
    
    public function closeDropdown()
    {
        $this->showDropdown = false;
    }
    
    public function toggleFilter()
    {
        $this->showOnlyUnread = !$this->showOnlyUnread;
    }
    
    public function markAsRead($notificationId)
    {
        // Marcar notificação do Laravel como lida
        auth()->user()->notifications()
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
    }
    
    public function markAllAsRead()
    {
        // Marcar todas as notificações não lidas como lidas
        auth()->user()->unreadNotifications->markAsRead();
    }
    
    public function deleteNotification($notificationId)
    {
        // Deletar notificação
        auth()->user()->notifications()
            ->where('id', $notificationId)
            ->delete();
    }
    
    public function deleteAllRead()
    {
        // Deletar todas as notificações lidas
        auth()->user()->notifications()
            ->whereNotNull('read_at')
            ->delete();
    }
    
    #[Computed]
    public function notifications()
    {
        $user = auth()->user();
        $allNotifications = [];
        
        if (!$user) {
            return collect($allNotifications);
        }
        
        // BUSCAR NOTIFICAÇÕES DO BANCO DE DADOS (Laravel Notifications)
        $dbNotifications = $user->notifications()
            ->when($this->showOnlyUnread, fn($q) => $q->whereNull('read_at'))
            ->latest()
            ->take(20)
            ->get()
            ->map(function($notification) {
                $data = $notification->data;
                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'info',
                    'icon' => $data['icon'] ?? 'fa-bell',
                    'color' => $data['color'] ?? 'blue',
                    'title' => $data['title'] ?? 'Notificação',
                    'message' => $data['message'] ?? '',
                    'time' => $notification->created_at->diffForHumans(),
                    'link' => $data['url'] ?? '#',
                    'is_read' => $notification->read_at !== null,
                    'is_database' => true,
                ];
            });
        
        $allNotifications = array_merge($allNotifications, $dbNotifications->toArray());
        
        // NOTIFICAÇÕES DINÂMICAS DO SISTEMA
        $systemNotifications = [];
        
        // 1. PLANO ATIVADO RECENTEMENTE (últimas 48h)
        $tenant = $user->activeTenant();
        if ($tenant) {
            $recentlyActivatedSubscription = $tenant->subscriptions()
                ->where('status', 'active')
                ->where('current_period_start', '>=', now()->subHours(48))
                ->orderBy('current_period_start', 'desc')
                ->first();
            
            if ($recentlyActivatedSubscription) {
                $systemNotifications[] = [
                    'type' => 'success',
                    'icon' => 'fa-check-circle',
                    'color' => 'green',
                    'title' => 'Plano Ativado!',
                    'message' => "Seu plano {$recentlyActivatedSubscription->plan->name} foi ativado com sucesso!",
                    'time' => $recentlyActivatedSubscription->current_period_start->diffForHumans(),
                    'link' => route('my-account') . '?tab=plan',
                    'is_read' => false,
                    'is_database' => false,
                ];
            }
        }
        
        // 2. SUBSCRIPTION EXPIRANDO (15 dias ou menos)
        if ($tenant) {
            $subscription = $tenant->activeSubscription;
            if ($subscription && $subscription->ends_at) {
                $daysRemaining = $subscription->ends_at->diffInDays(now());
                
                if ($subscription->ends_at->isFuture() && $daysRemaining <= 15) {
                    $color = $daysRemaining <= 3 ? 'red' : ($daysRemaining <= 7 ? 'orange' : 'yellow');
                    $icon = $daysRemaining <= 3 ? 'fa-exclamation-triangle' : 'fa-clock';
                    
                    $systemNotifications[] = [
                        'type' => 'warning',
                        'icon' => $icon,
                        'color' => $color,
                        'title' => $daysRemaining <= 3 ? 'Urgente: Subscription Expirando!' : 'Lembre-se de Renovar',
                        'message' => "Seu plano expira em {$daysRemaining} dia(s). Renove para continuar usando o sistema.",
                        'time' => $subscription->ends_at->diffForHumans(),
                        'link' => route('my-account') . '?tab=plan',
                        'is_read' => false,
                        'is_database' => false,
                    ];
                }
            }
        }
        
        // 3. PRODUTOS EXPIRADOS (últimos 7 dias)
        if ($tenant && $user->hasActiveModule('invoicing')) {
            $expiredCount = ProductBatch::where('tenant_id', $tenant->id)
                ->where('quantity_available', '>', 0)
                ->whereDate('expiry_date', '<', Carbon::now())
                ->whereDate('expiry_date', '>=', Carbon::now()->subDays(7))
                ->count();
            
            if ($expiredCount > 0) {
                $systemNotifications[] = [
                    'type' => 'danger',
                    'icon' => 'fa-times-circle',
                    'color' => 'red',
                    'title' => 'Produtos Expirados!',
                    'message' => "{$expiredCount} lote(s) de produtos já expiraram. Ação urgente necessária!",
                    'time' => 'Agora',
                    'link' => route('invoicing.expiry-report', ['reportType' => 'expired']),
                    'is_read' => false,
                    'is_database' => false,
                ];
            }
        }
        
        // 4. PRODUTOS EXPIRANDO EM BREVE (próximos 7 dias)
        if ($tenant && $user->hasActiveModule('invoicing')) {
            $expiringSoonCount = ProductBatch::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->whereDate('expiry_date', '<=', Carbon::now()->addDays(7))
                ->whereDate('expiry_date', '>=', Carbon::now())
                ->count();
            
            if ($expiringSoonCount > 0) {
                $systemNotifications[] = [
                    'type' => 'warning',
                    'icon' => 'fa-exclamation-triangle',
                    'color' => 'orange',
                    'title' => 'Produtos Expirando em Breve',
                    'message' => "{$expiringSoonCount} lote(s) de produtos expiram nos próximos 7 dias.",
                    'time' => 'Requer atenção',
                    'link' => route('invoicing.expiry-report', ['reportType' => 'expiring_soon']),
                    'is_read' => false,
                    'is_database' => false,
                ];
            }
        }
        
        // 5. PRODUTOS COM BAIXO STOCK (abaixo do mínimo)
        if ($tenant && $user->hasActiveModule('invoicing')) {
            $lowStockCount = Stock::where('tenant_id', $tenant->id)
                ->whereColumn('quantity', '<', 'minimum_quantity')
                ->where('minimum_quantity', '>', 0)
                ->count();
            
            if ($lowStockCount > 0) {
                $systemNotifications[] = [
                    'type' => 'warning',
                    'icon' => 'fa-box-open',
                    'color' => 'yellow',
                    'title' => 'Baixo Stock!',
                    'message' => "{$lowStockCount} produto(s) com estoque abaixo do mínimo.",
                    'time' => 'Requer reposição',
                    'link' => route('invoicing.stock'),
                    'is_read' => false,
                    'is_database' => false,
                ];
            }
        }
        
        // 6. PEDIDOS PENDENTES (Super Admin)
        if ($user->is_super_admin) {
            $pendingOrdersCount = Order::where('status', 'pending')->count();
            
            if ($pendingOrdersCount > 0) {
                $systemNotifications[] = [
                    'type' => 'info',
                    'icon' => 'fa-shopping-cart',
                    'color' => 'blue',
                    'title' => 'Pedidos Pendentes',
                    'message' => "{$pendingOrdersCount} pedido(s) aguardando aprovação.",
                    'time' => 'Requer atenção',
                    'link' => route('superadmin.billing'),
                    'is_read' => false,
                    'is_database' => false,
                ];
            }
        }
        
        // 7. FATURAS VENCIDAS (módulo de faturação)
        if ($tenant && $user->hasActiveModule('invoicing')) {
            $invoiceModel = $this->getInvoiceModel();
            
            if ($invoiceModel) {
                $overdueInvoices = $invoiceModel::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['pending', 'sent', 'partial'])
                    ->whereDate('due_date', '<', Carbon::today())
                    ->get();
                
                if ($overdueInvoices->isNotEmpty()) {
                    $totalOverdue = $overdueInvoices->sum('total');
                    $criticalCount = $overdueInvoices->filter(function($inv) {
                        return Carbon::parse($inv->due_date)->diffInDays(Carbon::today()) > 30;
                    })->count();
                    
                    $color = $criticalCount > 0 ? 'red' : 'orange';
                    $icon = $criticalCount > 0 ? 'fa-exclamation-triangle' : 'fa-exclamation-circle';
                    
                    $systemNotifications[] = [
                        'type' => 'danger',
                        'icon' => $icon,
                        'color' => $color,
                        'title' => 'Faturas Vencidas!',
                        'message' => "{$overdueInvoices->count()} fatura(s) vencida(s) totalizando " . number_format($totalOverdue, 2) . " Kz" . ($criticalCount > 0 ? " ({$criticalCount} críticas > 30 dias)" : ""),
                        'time' => 'Ação urgente',
                        'link' => route('invoicing.invoices') . '?status=overdue',
                        'is_read' => false,
                        'is_database' => false,
                    ];
                }
            }
        }
        
        // 8. FATURAS EXPIRANDO EM BREVE (próximos 7 dias)
        if ($tenant && $user->hasActiveModule('invoicing')) {
            $invoiceModel = $this->getInvoiceModel();
            
            if ($invoiceModel) {
                $expiringInvoices = $invoiceModel::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['pending', 'sent', 'partial'])
                    ->whereDate('due_date', '>', Carbon::today())
                    ->whereDate('due_date', '<=', Carbon::today()->addDays(7))
                    ->get();
                
                if ($expiringInvoices->isNotEmpty()) {
                    $totalExpiring = $expiringInvoices->sum('total');
                    $urgentCount = $expiringInvoices->filter(function($inv) {
                        return Carbon::today()->diffInDays(Carbon::parse($inv->due_date)) <= 3;
                    })->count();
                    
                    $color = $urgentCount > 0 ? 'orange' : 'yellow';
                    
                    $systemNotifications[] = [
                        'type' => 'warning',
                        'icon' => 'fa-clock',
                        'color' => $color,
                        'title' => 'Faturas Vencendo em Breve',
                        'message' => "{$expiringInvoices->count()} fatura(s) vencem nos próximos 7 dias - Total: " . number_format($totalExpiring, 2) . " Kz" . ($urgentCount > 0 ? " ({$urgentCount} em 3 dias)" : ""),
                        'time' => 'Lembrar clientes',
                        'link' => route('invoicing.invoices') . '?status=expiring',
                        'is_read' => false,
                        'is_database' => false,
                    ];
                }
            }
        }
        
        // 9. LIMITE DE EMPRESAS ATINGIDO
        if (!$user->is_super_admin) {
            $currentCount = $user->tenants()->count();
            $maxAllowed = $user->getMaxCompaniesLimit();
            
            if ($currentCount >= $maxAllowed && $maxAllowed < 999) {
                $systemNotifications[] = [
                    'type' => 'info',
                    'icon' => 'fa-building',
                    'color' => 'blue',
                    'title' => 'Limite de Empresas Atingido',
                    'message' => "Você atingiu o limite de {$maxAllowed} empresa(s). Faça upgrade para criar mais.",
                    'time' => 'Ação disponível',
                    'link' => route('my-account') . '?tab=plan',
                    'is_read' => false,
                    'is_database' => false,
                ];
            }
        }
        
        // Combinar notificações do banco de dados com notificações do sistema
        // Adicionar campos padrão para notificações do sistema
        foreach ($systemNotifications as &$notif) {
            if (!isset($notif['is_read'])) $notif['is_read'] = false;
            if (!isset($notif['is_database'])) $notif['is_database'] = false;
        }
        
        $allNotifications = array_merge($allNotifications, $systemNotifications);
        
        return collect($allNotifications);
    }
    
    #[Computed]
    public function unreadCount()
    {
        // Contar apenas não lidas do banco de dados
        $dbUnread = auth()->user()->unreadNotifications->count();
        
        // Contar notificações dinâmicas do sistema (sempre contam como não lidas)
        $systemUnread = $this->notifications()
            ->where('is_database', false)
            ->count();
        
        return $dbUnread + $systemUnread;
    }
    
    /**
     * Obter modelo de fatura correto
     */
    private function getInvoiceModel()
    {
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
    
    public function render()
    {
        return view('livewire.notifications');
    }
}
