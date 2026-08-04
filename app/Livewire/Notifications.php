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
        auth()->user()->notifications()
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
        $this->resetComputed();
    }

    public function markAllAsRead()
    {
        // Notificações do BD
        auth()->user()->unreadNotifications->markAsRead();
        // Notificações dinâmicas do sistema: marcar a "assinatura" atual como vista
        session(['notif_seen_signature' => $this->systemSignature()]);
        $this->resetComputed();
    }

    public function deleteNotification($notificationId)
    {
        auth()->user()->notifications()
            ->where('id', $notificationId)
            ->delete();
        $this->resetComputed();
    }

    public function deleteAllRead()
    {
        auth()->user()->notifications()
            ->whereNotNull('read_at')
            ->delete();
        $this->resetComputed();
    }

    /**
     * Limpar TODAS as notificações: apaga as do BD e marca as dinâmicas como vistas.
     */
    public function clearAll()
    {
        auth()->user()->notifications()->delete();
        session(['notif_seen_signature' => $this->systemSignature()]);
        $this->resetComputed();
    }

    /**
     * Atualizar a lista (recalcula tudo e revela alertas novos).
     */
    public function refreshNotifications()
    {
        // A própria ação já re-renderiza; basta limpar a cache das computed.
        $this->resetComputed();
    }

    private function resetComputed(): void
    {
        unset($this->notifications);
        unset($this->unreadCount);
    }

    #[Computed]
    public function notifications()
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        // ---- Notificações do BD (Laravel Notifications) ----
        $dbNotifications = $user->notifications()
            ->when($this->showOnlyUnread, fn($q) => $q->whereNull('read_at'))
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($notification) {
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
            })->toArray();

        // ---- Notificações dinâmicas do sistema ----
        $systemNotifications = $this->systemNotifications();

        // Estado "lido": comparar com a assinatura que o utilizador já viu
        $seen = session('notif_seen_signature');
        $systemRead = ($seen !== null && $seen === $this->systemSignature($systemNotifications));
        foreach ($systemNotifications as &$notif) {
            $notif['is_read'] = $systemRead;
            $notif['is_database'] = false;
        }
        unset($notif);

        // Se o filtro "só não lidas" está ativo e as dinâmicas já foram vistas, escondê-las
        if ($this->showOnlyUnread && $systemRead) {
            $systemNotifications = [];
        }

        return collect(array_merge($dbNotifications, $systemNotifications));
    }

    #[Computed]
    public function unreadCount()
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        $dbUnread = $user->unreadNotifications->count();

        $system = $this->systemNotifications();
        $seen = session('notif_seen_signature');
        $systemUnread = ($seen !== null && $seen === $this->systemSignature($system)) ? 0 : count($system);

        return $dbUnread + $systemUnread;
    }

    /**
     * Assinatura das notificações dinâmicas atuais (para saber se mudaram desde
     * que o utilizador as viu). Se nada mudar, ficam "lidas" e o badge zera.
     */
    protected function systemSignature(?array $system = null): string
    {
        $system = $system ?? $this->systemNotifications();
        return md5(collect($system)
            ->map(fn($n) => ($n['title'] ?? '') . '|' . ($n['message'] ?? ''))
            ->implode('||'));
    }

    /**
     * Calcula as notificações dinâmicas do sistema (em tempo real).
     */
    protected function systemNotifications(): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        $systemNotifications = [];
        $tenant = $user->activeTenant();

        // 1. PLANO ATIVADO RECENTEMENTE (últimas 48h)
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
                ];
            }
        }

        // 2. SUBSCRIPTION EXPIRANDO (15 dias ou menos)
        if ($tenant) {
            $subscription = $tenant->activeSubscription;
            if ($subscription && $subscription->ends_at && $subscription->ends_at->isFuture()) {
                $daysRemaining = (int) round(abs(now()->diffInDays($subscription->ends_at)));

                if ($daysRemaining <= 15) {
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
                    ];
                }
            }
        }

        // 3. PRODUTOS EXPIRADOS que AINDA ESTÃO EM STOCK
        if ($tenant && $user->hasActiveModule('invoicing')) {
            // Sem janela de sete dias. Ela existia e escondia o pior caso: um
            // lote que expirou há um mês e continua na prateleira é MAIS urgente
            // do que um que expirou ontem, e era exactamente esse que deixava de
            // aparecer. O `quantity_available > 0` já limita ao que está mesmo
            // em stock — o que foi vendido ou abatido não volta a incomodar.
            $expiredCount = ProductBatch::where('tenant_id', $tenant->id)
                ->where('quantity_available', '>', 0)
                ->whereDate('expiry_date', '<', Carbon::now())
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
                ];
            }
        }

        // 5. PRODUTOS COM BAIXO STOCK (abaixo do mínimo)
        if ($tenant && $user->hasActiveModule('invoicing')) {
            // O mínimo vive no PRODUTO (`invoicing_products.stock_min`), não na
            // linha de stock. A consulta anterior usava
            // `invoicing_stocks.minimum_quantity`, que está a ZERO nas 56.662
            // linhas da base — o aviso de baixo stock nunca disparou uma única
            // vez, enquanto havia 19.165 linhas abaixo do mínimo real. É a
            // mesma coluna que o ecrã de Gestão de Stock já usa.
            //
            // Query builder directo e colunas qualificadas: o global scope do
            // BelongsToTenant acrescenta `tenant_id` sem qualificar a tabela e,
            // com o join a `invoicing_products` (que também o tem), a consulta
            // rebentava com "column is ambiguous". A mesma armadilha que o
            // render() do ecrã de stock já documenta.
            $lowStockCount = \Illuminate\Support\Facades\DB::table('invoicing_stocks')
                ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
                ->where('invoicing_stocks.tenant_id', $tenant->id)
                ->where('invoicing_products.stock_min', '>', 0)
                ->where('invoicing_products.is_active', true)
                ->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min')
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
                    $criticalCount = $overdueInvoices->filter(function ($inv) {
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
                    $urgentCount = $expiringInvoices->filter(function ($inv) {
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
                ];
            }
        }

        return $systemNotifications;
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
