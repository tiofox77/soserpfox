<?php

namespace App\Livewire\SuperAdmin;

use App\Models\AnalyticsEvent;
use App\Services\Analytics\Origem;
use App\Services\Analytics\Regiao;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Analytics')]
class Analytics extends Component
{
    /** Quem está no site AGORA: visto nos últimos cinco minutos. */
    private const MINUTOS_ONLINE = 5;

    public string $range = '7d';   // today, 7d, 30d, 90d, all, custom
    public string $dataDe = '';
    public string $dataAte = '';

    public string $deviceFilter  = '';
    public string $sourceFilter  = '';   // canal: directo, orgânico, social, ...
    public string $countryFilter = '';
    public string $browserFilter = '';
    public string $pathFilter    = '';

    public string $tab = 'overview';

    /** Visitante aberto em detalhe — o percurso dele, página a página. */
    public ?string $visitanteAberto = null;

    public function setRange(string $r): void
    {
        $this->range = $r;

        if ($r !== 'custom') {
            $this->reset(['dataDe', 'dataAte']);
        }
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
    }

    public function limparFiltros(): void
    {
        $this->reset(['deviceFilter', 'sourceFilter', 'countryFilter', 'browserFilter', 'pathFilter']);
    }

    public function verVisitante(string $visitorId): void
    {
        $this->visitanteAberto = $visitorId;
    }

    public function fecharVisitante(): void
    {
        $this->visitanteAberto = null;
    }

    /** Há filtros para além do período? */
    public function getTemFiltrosProperty(): bool
    {
        return (bool) ($this->deviceFilter || $this->sourceFilter || $this->countryFilter
            || $this->browserFilter || $this->pathFilter);
    }

    protected function dateFrom(): Carbon
    {
        if ($this->range === 'custom' && $this->dataDe) {
            return Carbon::parse($this->dataDe)->startOfDay();
        }

        return match ($this->range) {
            'today' => Carbon::today(),
            '7d'    => Carbon::now()->subDays(7),
            '30d'   => Carbon::now()->subDays(30),
            '90d'   => Carbon::now()->subDays(90),
            'all'   => Carbon::now()->subYears(5),
            default => Carbon::now()->subDays(7),
        };
    }

    protected function dateTo(): Carbon
    {
        if ($this->range === 'custom' && $this->dataAte) {
            return Carbon::parse($this->dataAte)->endOfDay();
        }

        return Carbon::now();
    }

    /**
     * A consulta com os filtros todos aplicados.
     *
     * O filtro de canal (directo/orgânico/social) NÃO se faz aqui: um canal
     * deriva-se do domínio do referrer, e um domínio não se extrai com LIKE.
     * Aplica-se em PHP, sobre o conjunto já reduzido pelos outros filtros —
     * ver `visitantesDoCanal()`.
     */
    protected function baseQuery()
    {
        $q = AnalyticsEvent::whereBetween('created_at', [$this->dateFrom(), $this->dateTo()]);

        if ($this->deviceFilter)  { $q->where('device_type', $this->deviceFilter); }
        if ($this->countryFilter) { $q->where('country', $this->countryFilter); }
        if ($this->browserFilter) { $q->where('browser', $this->browserFilter); }
        if ($this->pathFilter)    { $q->where('path', $this->pathFilter); }

        if ($this->sourceFilter) {
            $q->whereIn('visitor_id', $this->visitantesDoCanal());
        }

        return $q;
    }

    /**
     * Os visitantes cujo canal de entrada é o filtrado.
     *
     * Classifica-se a PRIMEIRA visita de cada um: o canal de um visitante é
     * por onde ele entrou, não por onde andou depois. Alguém que chega pelo
     * Facebook e navega no site não passa a "interno" à segunda página.
     */
    protected function visitantesDoCanal(): array
    {
        $primeiras = AnalyticsEvent::whereBetween('created_at', [$this->dateFrom(), $this->dateTo()])
            ->select('visitor_id', DB::raw('MIN(id) as primeiro'))
            ->groupBy('visitor_id')
            ->pluck('primeiro');

        if ($primeiras->isEmpty()) {
            return [];
        }

        $proprio = parse_url(config('app.url'), PHP_URL_HOST);

        return AnalyticsEvent::whereIn('id', $primeiras)
            ->select('visitor_id', 'referrer', 'utm_source')
            ->get()
            ->filter(fn ($e) => Origem::classificar($e->referrer, $e->utm_source, $proprio)['canal'] === $this->sourceFilter)
            ->pluck('visitor_id')
            ->all();
    }

    public function render()
    {
        $de  = $this->dateFrom();
        $ate = $this->dateTo();
        $proprio = parse_url(config('app.url'), PHP_URL_HOST);

        // ── Quem está aqui agora ────────────────────────────────────────────
        // Sem período nem filtros: "agora" é agora.
        $agoraDesde = Carbon::now()->subMinutes(self::MINUTOS_ONLINE);

        $online = AnalyticsEvent::where('created_at', '>=', $agoraDesde)
            ->distinct()->count('visitor_id');

        $onlinePaginas = AnalyticsEvent::where('created_at', '>=', $agoraDesde)
            ->where('type', 'pageview')
            ->select('path', DB::raw('COUNT(DISTINCT visitor_id) as visitantes'))
            ->groupBy('path')
            ->orderByDesc('visitantes')
            ->limit(6)
            ->get();

        // ── Números do período ──────────────────────────────────────────────
        //
        // Os nove numa consulta só, com agregados condicionais. Eram nove
        // varreduras da mesma tabela, com os mesmos filtros, para responder a
        // nove perguntas que cabem numa linha.
        $k = (clone $this->baseQuery())
            ->selectRaw('
                COUNT(DISTINCT visitor_id)                                          as visitantes,
                COUNT(DISTINCT session_id)                                          as sessoes,
                SUM(CASE WHEN type = "pageview"  THEN 1 ELSE 0 END)                 as pageviews,
                SUM(CASE WHEN type = "cta_click" THEN 1 ELSE 0 END)                 as cliques,
                SUM(CASE WHEN type = "search"    THEN 1 ELSE 0 END)                 as pesquisas,
                SUM(CASE WHEN event_name = "click_register" THEN 1 ELSE 0 END)      as registos,
                SUM(CASE WHEN event_name = "click_whatsapp" THEN 1 ELSE 0 END)      as whatsapp,
                SUM(CASE WHEN event_name = "click_module"   THEN 1 ELSE 0 END)      as modulos,
                COUNT(*)                                                            as eventos
            ')
            ->first();

        $totalVisitors  = (int) $k->visitantes;
        $totalSessions  = (int) $k->sessoes;
        $totalPageviews = (int) $k->pageviews;
        $totalCtaClicks = (int) $k->cliques;
        $totalSearches  = (int) $k->pesquisas;
        $registerClicks = (int) $k->registos;
        $whatsappClicks = (int) $k->whatsapp;
        $moduleClicks   = (int) $k->modulos;
        $totalEvents    = (int) $k->eventos;

        $convRate = $totalVisitors > 0 ? round($registerClicks / $totalVisitors * 100, 2) : 0;

        // Páginas por sessão: mede se as pessoas navegam ou saem à primeira.
        $paginasPorSessao = $totalSessions > 0 ? round($totalPageviews / $totalSessions, 1) : 0;

        // Sessões de uma só página. É a taxa de rejeição.
        $sessoesUmaPagina = DB::query()
            ->fromSub(
                (clone $this->baseQuery())->where('type', 'pageview')
                    ->select('session_id', DB::raw('COUNT(*) as n'))
                    ->groupBy('session_id'),
                'p'
            )
            ->where('n', 1)
            ->count();

        $taxaRejeicao = $totalSessions > 0 ? round($sessoesUmaPagina / $totalSessions * 100) : 0;

        // ── Comparação com o período anterior ───────────────────────────────
        $duracao  = $de->diffInSeconds($ate);
        $prevDe   = $de->copy()->subSeconds($duracao);
        $prevVisitors = AnalyticsEvent::whereBetween('created_at', [$prevDe, $de])
            ->distinct()->count('visitor_id');
        $visitorsTrend = $prevVisitors > 0
            ? round(($totalVisitors - $prevVisitors) / $prevVisitors * 100, 1)
            : 0;

        // ── Páginas ─────────────────────────────────────────────────────────
        $topPages = (clone $this->baseQuery())
            ->where('type', 'pageview')
            ->select(
                'path',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT visitor_id) as visitors'),
                DB::raw('AVG(duration_seconds) as tempo_medio')
            )
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(15)
            ->get();

        // ── De onde vieram ──────────────────────────────────────────────────
        // Uma linha por visitante (a primeira visita dele), classificada em
        // PHP. O CASE em SQL que aqui esteve juntava tudo o que não fosse
        // google/facebook/instagram num "other" que não dizia nada — e contava
        // o tráfego da própria aplicação como se viesse de fora.
        $primeiras = (clone $this->baseQuery())
            ->select('visitor_id', DB::raw('MIN(id) as primeiro'))
            ->groupBy('visitor_id')
            ->pluck('primeiro');

        $entradas = $primeiras->isEmpty()
            ? collect()
            : AnalyticsEvent::whereIn('id', $primeiras)->select('referrer', 'utm_source')->get();

        $classificadas = $entradas->map(fn ($e) => Origem::classificar($e->referrer, $e->utm_source, $proprio));

        $canais = $classificadas
            ->groupBy('canal')
            ->map->count()
            ->sortDesc();

        $topSources = $classificadas
            ->groupBy('fonte')
            ->map(fn ($g, $fonte) => [
                'fonte'     => $fonte,
                'canal'     => $g->first()['canal'],
                'visitantes' => $g->count(),
            ])
            ->sortByDesc('visitantes')
            ->take(12)
            ->values();

        // ── Região ──────────────────────────────────────────────────────────
        $countries = (clone $this->baseQuery())
            ->select('country', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(12)
            ->get();

        $cities = (clone $this->baseQuery())
            ->select('city', 'country', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->whereNotNull('city')
            ->where('city', '!=', 'Rede local')
            ->groupBy('city', 'country')
            ->orderByDesc('count')
            ->limit(12)
            ->get();

        $regiaoPorResolver = Regiao::porResolver();

        // ── O que procuram ──────────────────────────────────────────────────
        $topSearches = (clone $this->baseQuery())
            ->where('type', 'search')
            ->whereNotNull('search_term')
            ->select(
                'search_term',
                DB::raw('COUNT(*) as vezes'),
                DB::raw('COUNT(DISTINCT visitor_id) as pessoas'),
                DB::raw('MAX(created_at) as ultima')
            )
            ->groupBy('search_term')
            ->orderByDesc('vezes')
            ->limit(20)
            ->get();

        // ── Aparelhos ───────────────────────────────────────────────────────
        $devices = (clone $this->baseQuery())
            ->select('device_type', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();

        $browsers = (clone $this->baseQuery())
            ->select('browser', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $sistemas = (clone $this->baseQuery())
            ->select('os', DB::raw('COUNT(DISTINCT visitor_id) as count'))
            ->whereNotNull('os')
            ->groupBy('os')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        // ── Série temporal ──────────────────────────────────────────────────
        // UMA consulta agrupada por dia. Aqui estava um ciclo que fazia uma
        // consulta POR DIA do período — trinta e uma consultas para desenhar
        // um gráfico, e a cada render.
        $porDia = (clone $this->baseQuery())
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(DISTINCT visitor_id) as visitantes'))
            ->groupBy('dia')
            ->pluck('visitantes', 'dia');

        $days = [];
        $cursor = $de->copy()->startOfDay();
        $fim    = $ate->copy()->startOfDay();
        $limite = 0;

        while ($cursor->lte($fim) && $limite++ < 92) {
            $chave = $cursor->toDateString();
            $days[] = ['label' => $cursor->format('d/m'), 'value' => (int) ($porDia[$chave] ?? 0)];
            $cursor->addDay();
        }

        // ── Funil ───────────────────────────────────────────────────────────
        // Os quatro degraus numa consulta, com COUNT(DISTINCT ... CASE): eram
        // quatro varreduras da mesma tabela com o mesmo filtro.
        $f = (clone $this->baseQuery())
            ->selectRaw('
                COUNT(DISTINCT CASE WHEN path = "/"                       THEN visitor_id END) as landing,
                COUNT(DISTINCT CASE WHEN path LIKE "/modulos%"            THEN visitor_id END) as modulos,
                COUNT(DISTINCT CASE WHEN event_name = "click_planos"      THEN visitor_id END) as planos,
                COUNT(DISTINCT CASE WHEN event_name = "click_register"    THEN visitor_id END) as registo
            ')
            ->first();

        $funnel = [
            ['stage' => 'Landing',         'count' => (int) $f->landing, 'icon' => 'fa-house',  'color' => '#3b82f6'],
            ['stage' => 'Viu Módulos',     'count' => (int) $f->modulos, 'icon' => 'fa-cubes',  'color' => '#8b5cf6'],
            ['stage' => 'Clicou Planos',   'count' => (int) $f->planos,  'icon' => 'fa-tag',    'color' => '#ec4899'],
            ['stage' => 'Iniciou Registo', 'count' => (int) $f->registo, 'icon' => 'fa-rocket', 'color' => '#10b981'],
        ];

        // ── Visitantes, por interesse ───────────────────────────────────────
        $leads = (clone $this->baseQuery())
            ->select(
                'visitor_id',
                DB::raw('COUNT(*) as total_events'),
                DB::raw("SUM(CASE WHEN type='pageview' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("SUM(CASE WHEN type='cta_click' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN event_name='click_register' THEN 30 WHEN event_name='click_whatsapp' THEN 15 WHEN event_name='click_module' THEN 3 WHEN type='cta_click' THEN 5 ELSE 1 END) as score"),
                DB::raw('MAX(created_at) as last_seen'),
                DB::raw('MIN(created_at) as first_seen'),
                DB::raw('MAX(country) as country'),
                DB::raw('MAX(city) as city'),
                DB::raw('MAX(device_type) as device'),
                DB::raw('MAX(browser) as browser'),
                DB::raw('MAX(utm_source) as utm_source'),
                DB::raw('MAX(referrer) as referrer'),
                DB::raw('COUNT(DISTINCT path) as pages_visited'),
                DB::raw('SUM(duration_seconds) as tempo_total')
            )
            ->groupBy('visitor_id')
            ->orderByDesc('score')
            ->limit(50)
            ->get();

        // ── Percurso de um visitante ────────────────────────────────────────
        $percurso = collect();

        if ($this->visitanteAberto) {
            $percurso = AnalyticsEvent::where('visitor_id', $this->visitanteAberto)
                ->orderBy('created_at')
                ->limit(200)
                ->get();
        }

        // ── Ao vivo ─────────────────────────────────────────────────────────
        $recentEvents = (clone $this->baseQuery())
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        $eventBreakdown = (clone $this->baseQuery())
            ->where('type', 'cta_click')
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        // $totalEvents já veio no bloco de agregados acima.

        // ── Opções dos filtros, tiradas do que existe ───────────────────────
        $paisesDisponiveis = AnalyticsEvent::whereBetween('created_at', [$de, $ate])
            ->whereNotNull('country')->distinct()->orderBy('country')->pluck('country');

        $browsersDisponiveis = AnalyticsEvent::whereBetween('created_at', [$de, $ate])
            ->whereNotNull('browser')->distinct()->orderBy('browser')->pluck('browser');

        $paginasDisponiveis = AnalyticsEvent::whereBetween('created_at', [$de, $ate])
            ->where('type', 'pageview')->whereNotNull('path')
            ->distinct()->orderBy('path')->limit(60)->pluck('path');

        return view('livewire.super-admin.analytics', compact(
            'online', 'onlinePaginas',
            'totalVisitors', 'totalSessions', 'totalPageviews', 'totalCtaClicks', 'totalSearches',
            'registerClicks', 'whatsappClicks', 'moduleClicks', 'convRate',
            'paginasPorSessao', 'taxaRejeicao', 'visitorsTrend',
            'topPages', 'canais', 'topSources', 'countries', 'cities', 'regiaoPorResolver',
            'topSearches', 'devices', 'browsers', 'sistemas', 'days', 'funnel',
            'leads', 'percurso', 'recentEvents', 'eventBreakdown', 'totalEvents',
            'paisesDisponiveis', 'browsersDisponiveis', 'paginasDisponiveis'
        ));
    }
}
