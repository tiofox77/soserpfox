<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Order;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class Notifications extends Component
{
    public $showDropdown = false;
    public $showOnlyUnread = true;
    
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
        $notification = UserNotification::where('user_id', auth()->id())
            ->find($notificationId);
        
        if ($notification) {
            $notification->markAsRead();
        }
    }
    
    public function markAllAsRead()
    {
        UserNotification::where('user_id', auth()->id())
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
    
    public function deleteNotification($notificationId)
    {
        UserNotification::where('user_id', auth()->id())
            ->find($notificationId)
            ?->delete();
    }
    
    #[Computed]
    public function notifications()
    {
        $user = auth()->user();
        $notifications = [];
        
        if (!$user) {
            return $notifications;
        }
        
        // 1. PLANO ATIVADO RECENTEMENTE (últimas 48h)
        $tenant = $user->activeTenant();
        if ($tenant) {
            $recentlyActivatedSubscription = $tenant->subscriptions()
                ->where('status', 'active')
                ->where('current_period_start', '>=', now()->subHours(48))
                ->orderBy('current_period_start', 'desc')
                ->first();
            
            if ($recentlyActivatedSubscription) {
                $notifications[] = [
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
            if ($subscription && $subscription->ends_at) {
                $daysRemaining = $subscription->ends_at->diffInDays(now());
                
                if ($subscription->ends_at->isFuture() && $daysRemaining <= 15) {
                    $color = $daysRemaining <= 3 ? 'red' : ($daysRemaining <= 7 ? 'orange' : 'yellow');
                    $icon = $daysRemaining <= 3 ? 'fa-exclamation-triangle' : 'fa-clock';
                    
                    $notifications[] = [
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
        
        // 3. PRODUTOS EXPIRADOS/EXPIRANDO (REMOVIDO TEMPORARIAMENTE - implementar lógica de validade)
        
        // 4. PEDIDOS PENDENTES (Super Admin)
        if ($user->is_super_admin) {
            $pendingOrdersCount = Order::where('status', 'pending')->count();
            
            if ($pendingOrdersCount > 0) {
                $notifications[] = [
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
        
        // 5. LIMITE DE EMPRESAS ATINGIDO
        if (!$user->is_super_admin) {
            $currentCount = $user->tenants()->count();
            $maxAllowed = $user->getMaxCompaniesLimit();
            
            if ($currentCount >= $maxAllowed && $maxAllowed < 999) {
                $notifications[] = [
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
        
        return collect($notifications);
    }
    
    #[Computed]
    public function unreadCount()
    {
        return $this->notifications()->count();
    }
    
    public function render()
    {
        return view('livewire.notifications');
    }
}
