<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = auth()->user();
        $activeSubscription = null;
        $activeTenant = null;
        $debug = [];
        
        // Verificar se tem tenant ativo
        $hasCompany = $user->tenants()->count() > 0;
        $activeTenant = $user->activeTenant();
        
        // Verificar status de subscription
        $hasActiveSubscription = false;
        $subscriptionStatus = null;
        
        if ($activeTenant) {
            $activeSubscription = $activeTenant->subscriptions()
                ->with('plan')
                ->whereIn('status', ['active', 'trial'])
                ->latest()
                ->first();
                
            $hasActiveSubscription = $activeSubscription !== null;
            $subscriptionStatus = $activeSubscription->status ?? null;
                
            // Debug info
            $debug['user_id'] = $user->id;
            $debug['user_email'] = $user->email;
            $debug['tenant_id'] = $activeTenant->id;
            $debug['tenant_name'] = $activeTenant->name;
            $debug['has_subscription'] = $hasActiveSubscription;
            $debug['subscription_status'] = $subscriptionStatus;
            $debug['modules_count'] = $activeTenant->modules()->count();
            $debug['active_modules'] = $activeTenant->modules()
                ->wherePivot('is_active', true)
                ->pluck('name', 'slug')
                ->toArray();
        } else {
            $debug['warning'] = 'Usuário não tem tenant ativo';
        }
        
        // Alertas
        $needsCompany = !$hasCompany;
        $needsSubscription = $hasCompany && !$hasActiveSubscription;
        
        // Verificar pedidos pendentes
        $hasPendingOrder = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
        
        return view('home', compact(
            'activeSubscription', 
            'debug', 
            'hasCompany', 
            'needsCompany', 
            'needsSubscription',
            'activeTenant',
            'subscriptionStatus',
            'hasPendingOrder'
        ));
    }
}
