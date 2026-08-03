<?php

namespace App\Livewire\SuperAdmin;

use App\Models\AnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Analytics & Leads')]
class Analytics extends Component
{
    public string $range = '7d';   // today, 7d, 30d, 90d, all
    public string $deviceFilter = '';
    public string $sourceFilter = '';
    public string $tab = 'overview';

    public function setRange(string $r) { $this->range = $r; }
    public function setTab(string $t) { $this->tab = $t; }

    protected function dateFrom(): Carbon
    {
        return match($this->range) {
            'today' => Carbon::today(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'all' => Carbon::now()->subYears(5),
            default => Carbon::now()->subDays(7),
        };
    }

    protected function baseQuery()
    {
        $q = AnalyticsEvent::where('created_at', '>=', $this->dateFrom());
        if ($this->deviceFilter) $q->where('device_type', $this->deviceFilter);
        if ($this->sourceFilter) $q->where('utm_source', $this->sourceFilter);
        return $q;
    }

    public function render()
    {
        $from = $this->dateFrom();

        // KPIs
        $totalVisitors = (clone $this->baseQuery())->distinct('visitor_id')->count('visitor_id');
        $totalSessions = (clone $this->baseQuery())->distinct('session_id')->count('session_id');
        $totalPageviews = (clone $this->baseQuery())->where('type', 'pageview')->count();
        $totalCtaClicks = (clone $this->baseQuery())->where('type', 'cta_click')->count();
        $registerClicks = (clone $this->baseQuery())->where('event_name', 'click_register')->count();
        $whatsappClicks = (clone $this->baseQuery())->where('event_name', 'click_whatsapp')->count();
        $moduleClicks = (clone $this->baseQuery())->where('event_name', 'click_module')->count();
        $convRate = $totalVisitors > 0 ? round($registerClicks / $totalVisitors * 100, 2) : 0;

        // Período comparativo (anterior)
        $prevFrom = $from->copy()->subSeconds($from->diffInSeconds(now()));
        $prevTo = $from;
        $prevVisitors = AnalyticsEvent::whereBetween('created_at', [$prevFrom, $prevTo])->distinct('visitor_id')->count('visitor_id');
        $visitorsTrend = $prevVisitors > 0 ? round(($totalVisitors - $prevVisitors) / $prevVisitors * 100, 1) : 0;

        // Top páginas
        $topPages = (clone $this->baseQuery())
            ->where('type', 'pageview')
            ->select('path', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_id) as visitors'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Top sources (referrer + utm)
        $topSources = (clone $this->baseQuery())
            ->where('type', 'pageview')
            ->select(DB::raw("COALESCE(utm_source, CASE
                WHEN referrer = '' OR referrer IS NULL THEN 'direct'
                WHEN referrer LIKE '%google%' THEN 'google'
                WHEN referrer LIKE '%facebook%' THEN 'facebook'
                WHEN referrer LIKE '%instagram%' THEN 'instagram'
                WHEN referrer LIKE '%linkedin%' THEN 'linkedin'
                WHEN referrer LIKE '%whatsapp%' THEN 'whatsapp'
                ELSE 'other'
            END) as source"), DB::raw('COUNT(DISTINCT visitor_id) as visitors'))
            ->groupBy('source')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get();

        // Devices
        $devices = (clone $this->baseQuery())
            ->select('device_type', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->groupBy('device_type')
            ->get();

        $browsers = (clone $this->baseQuery())
            ->select('browser', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        // Países
        $countries = (clone $this->baseQuery())
            ->select('country', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Série temporal (visitas por dia)
        $days = [];
        $sinceDays = (int) max(1, ceil(abs($from->diffInDays(now()))));
        $sinceDays = min($sinceDays, 30);
        for ($i = $sinceDays; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $count = AnalyticsEvent::whereDate('created_at', $d)
                ->when($this->deviceFilter, fn($q) => $q->where('device_type', $this->deviceFilter))
                ->distinct('visitor_id')->count('visitor_id');
            $days[] = ['label' => $d->format('d/m'), 'value' => $count];
        }

        // Funil de conversão
        $funnelLanding = (clone $this->baseQuery())->where('path', '/')->distinct('visitor_id')->count('visitor_id');
        $funnelModulos = (clone $this->baseQuery())->where('path', 'like', '/modulos%')->distinct('visitor_id')->count('visitor_id');
        $funnelPlanos = (clone $this->baseQuery())->where('event_name', 'click_planos')->distinct('visitor_id')->count('visitor_id');
        $funnelRegister = (clone $this->baseQuery())->where('event_name', 'click_register')->distinct('visitor_id')->count('visitor_id');

        $funnel = [
            ['stage' => 'Landing', 'count' => $funnelLanding, 'icon' => 'fa-house', 'color' => '#3b82f6'],
            ['stage' => 'Viu Módulos', 'count' => $funnelModulos, 'icon' => 'fa-cubes', 'color' => '#8b5cf6'],
            ['stage' => 'Clicou Planos', 'count' => $funnelPlanos, 'icon' => 'fa-tag', 'color' => '#ec4899'],
            ['stage' => 'Iniciou Registo', 'count' => $funnelRegister, 'icon' => 'fa-rocket', 'color' => '#10b981'],
        ];

        // ===== LEADS — visitantes com maior score =====
        // Score = pageviews*1 + cta_clicks*5 + register_clicks*30 + whatsapp_clicks*15 + módulo views*3
        $leads = AnalyticsEvent::where('created_at', '>=', $from)
            ->select(
                'visitor_id',
                DB::raw("COUNT(*) as total_events"),
                DB::raw("SUM(CASE WHEN type='pageview' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("SUM(CASE WHEN type='cta_click' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN event_name='click_register' THEN 30 WHEN event_name='click_whatsapp' THEN 15 WHEN event_name='click_module' THEN 3 WHEN type='cta_click' THEN 5 ELSE 1 END) as score"),
                DB::raw("MAX(created_at) as last_seen"),
                DB::raw("MIN(created_at) as first_seen"),
                DB::raw("MAX(country) as country"),
                DB::raw("MAX(device_type) as device"),
                DB::raw("MAX(browser) as browser"),
                DB::raw("MAX(utm_source) as utm_source"),
                DB::raw("MAX(referrer) as referrer"),
                DB::raw("COUNT(DISTINCT path) as pages_visited")
            )
            ->groupBy('visitor_id')
            ->orderByDesc('score')
            ->limit(50)
            ->get();

        // Eventos recentes (live feed)
        $recentEvents = (clone $this->baseQuery())
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        // Cliques por evento
        $eventBreakdown = (clone $this->baseQuery())
            ->where('type', 'cta_click')
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        // Total raw events
        $totalEvents = (clone $this->baseQuery())->count();

        return view('livewire.super-admin.analytics', compact(
            'totalVisitors', 'totalSessions', 'totalPageviews', 'totalCtaClicks',
            'registerClicks', 'whatsappClicks', 'moduleClicks', 'convRate',
            'visitorsTrend', 'topPages', 'topSources', 'devices', 'browsers',
            'countries', 'days', 'funnel', 'leads', 'recentEvents',
            'eventBreakdown', 'totalEvents'
        ));
    }
}
