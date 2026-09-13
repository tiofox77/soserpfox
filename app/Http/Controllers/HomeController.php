<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        // ====== SUPER ADMIN: home dedicado com analytics ======
        if ($user->isSuperAdmin() && !session()->has('impersonate_tenant_id')) {
            return $this->superAdminHome($user);
        }

        // O ecrã em React (`inicio`) pede o que mostra a /api/v1/casca/inicio —
        // ver App\Services\Casca\PaginaInicial. Daqui só vai o recado da sessão.
        return \App\Support\EcraReact::pagina('inicio', 'Início', ['estado' => session('status')])();
    }

    /**
     * Dashboard rico de boas-vindas para o Super Admin (/home).
     */
    protected function superAdminHome($user)
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $lastMonth = $now->copy()->subMonth();

        // ===== Tenants =====
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $trialTenants = Subscription::where('status', 'trial')->distinct('tenant_id')->count('tenant_id');
        $newTenantsToday = Tenant::whereDate('created_at', $today)->count();
        $newTenants7d = Tenant::where('created_at', '>=', $now->copy()->subDays(7))->count();
        $newTenants30d = Tenant::where('created_at', '>=', $now->copy()->subDays(30))->count();
        $newTenantsPrev30d = Tenant::whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
        $tenantGrowth = $newTenantsPrev30d > 0 ? round(($newTenants30d - $newTenantsPrev30d) / $newTenantsPrev30d * 100, 1) : ($newTenants30d > 0 ? 100 : 0);

        // ===== Utilizadores =====
        $totalUsers = User::where('is_super_admin', false)->count();
        $newUsersToday = User::whereDate('created_at', $today)->count();
        $activeUsersToday = User::whereDate('last_login_at', $today)->count();

        // ===== Receita / Billing =====
        $totalRevenue = 0; $monthlyRevenue = 0; $pendingRevenue = 0; $mrr = 0;
        try {
            $totalRevenue = (float) Invoice::where('status', 'paid')->sum('total');
            $monthlyRevenue = (float) Invoice::where('status', 'paid')
                ->whereMonth('paid_at', $now->month)->whereYear('paid_at', $now->year)->sum('total');
            $pendingRevenue = (float) Invoice::where('status', 'pending')->sum('total');
            // MRR aproximado: soma de planos ativos com preço mensal
            $mrr = (float) DB::table('subscriptions')
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->where('subscriptions.status', 'active')
                ->sum('plans.price_monthly');
        } catch (\Exception $e) {}

        // ===== Analytics (landing page) =====
        $analyticsAvailable = false; $visitors30d = 0; $pageviews30d = 0; $ctaClicks30d = 0; $registerClicks30d = 0;
        $topPages = collect(); $topSources = collect(); $hotLeads = collect();
        try {
            if (\Schema::hasTable('analytics_events')) {
                $analyticsAvailable = true;
                $from30 = $now->copy()->subDays(30);
                $visitors30d = AnalyticsEvent::where('created_at', '>=', $from30)->distinct('visitor_id')->count('visitor_id');
                $pageviews30d = AnalyticsEvent::where('created_at', '>=', $from30)->where('type', 'pageview')->count();
                $ctaClicks30d = AnalyticsEvent::where('created_at', '>=', $from30)->where('type', 'cta_click')->count();
                $registerClicks30d = AnalyticsEvent::where('created_at', '>=', $from30)->where('event_name', 'click_register')->count();

                $topPages = AnalyticsEvent::where('created_at', '>=', $from30)
                    ->where('type', 'pageview')
                    ->select('path', DB::raw('COUNT(*) as views'))
                    ->groupBy('path')->orderByDesc('views')->limit(5)->get();

                $topSources = AnalyticsEvent::where('created_at', '>=', $from30)
                    ->where('type', 'pageview')
                    ->select(DB::raw("COALESCE(utm_source, CASE
                        WHEN referrer = '' OR referrer IS NULL THEN 'direct'
                        WHEN referrer LIKE '%google%' THEN 'google'
                        WHEN referrer LIKE '%facebook%' THEN 'facebook'
                        WHEN referrer LIKE '%whatsapp%' THEN 'whatsapp'
                        ELSE 'other' END) as source"), DB::raw('COUNT(DISTINCT visitor_id) as v'))
                    ->groupBy('source')->orderByDesc('v')->limit(5)->get();

                // Top 5 leads pelo score
                $hotLeads = AnalyticsEvent::where('created_at', '>=', $from30)
                    ->select('visitor_id',
                        DB::raw("SUM(CASE WHEN event_name='click_register' THEN 30 WHEN event_name='click_whatsapp' THEN 15 WHEN event_name='click_module' THEN 3 WHEN type='cta_click' THEN 5 ELSE 1 END) as score"),
                        DB::raw('MAX(created_at) as last_seen'),
                        DB::raw('MAX(country) as country'),
                        DB::raw('MAX(device_type) as device'),
                        DB::raw('MAX(utm_source) as utm_source'))
                    ->groupBy('visitor_id')->orderByDesc('score')->limit(5)->get();
            }
        } catch (\Exception $e) {}

        // ===== Pedidos pendentes (orders) =====
        $pendingOrders = 0;
        try { $pendingOrders = Order::where('status', 'pending')->count(); } catch (\Exception $e) {}

        // ===== Tenants recentes =====
        $recentTenants = Tenant::orderByDesc('created_at')->limit(8)->get();

        // ===== Série temporal: novos tenants nos últimos 14 dias =====
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $series[] = [
                'label' => $d->format('d/m'),
                'tenants' => Tenant::whereDate('created_at', $d)->count(),
                'users' => User::whereDate('created_at', $d)->count(),
            ];
        }

        // ===== Info do sistema =====
        $systemInfo = [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'env' => app()->environment(),
            'debug' => config('app.debug') ? 'ON' : 'OFF',
            'tz' => config('app.timezone'),
            'db' => config('database.default'),
            'cache' => config('cache.default'),
            'queue' => config('queue.default'),
        ];

        return view('home-superadmin', compact(
            'user',
            'totalTenants', 'activeTenants', 'trialTenants',
            'newTenantsToday', 'newTenants7d', 'newTenants30d', 'tenantGrowth',
            'totalUsers', 'newUsersToday', 'activeUsersToday',
            'totalRevenue', 'monthlyRevenue', 'pendingRevenue', 'mrr',
            'analyticsAvailable', 'visitors30d', 'pageviews30d', 'ctaClicks30d', 'registerClicks30d',
            'topPages', 'topSources', 'hotLeads',
            'pendingOrders', 'recentTenants', 'series', 'systemInfo'
        ));
    }
}
