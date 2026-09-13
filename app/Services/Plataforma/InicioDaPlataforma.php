<?php

namespace App\Services\Plataforma;

use App\Models\AnalyticsEvent;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A PÁGINA INICIAL DO SUPER ADMIN (`/home`) — as boas-vindas com os números
 * da plataforma, o crescimento dos últimos 14 dias, os leads da página
 * inicial, as empresas recentes e o estado do sistema.
 *
 * Estava tudo no HomeController, a alimentar um Blade. As contas são as de
 * sempre; o que muda é que chegam por API a um ecrã em React.
 */
class InicioDaPlataforma
{
    public function dados(User $user): array
    {
        $agora = Carbon::now();
        $hoje = Carbon::today();

        $novos30 = Tenant::where('created_at', '>=', $agora->copy()->subDays(30))->count();
        $novos30Antes = Tenant::whereBetween('created_at', [$agora->copy()->subDays(60), $agora->copy()->subDays(30)])->count();

        return [
            'utilizador' => ['nome' => $user->name],
            'agora' => $agora->toIso8601String(),
            'empresas' => [
                'total' => Tenant::count(),
                'activas' => Tenant::where('is_active', true)->count(),
                'em_teste' => DB::table('subscriptions')->where('status', 'trial')->distinct()->count('tenant_id'),
                'hoje' => Tenant::whereDate('created_at', $hoje)->count(),
                'semana' => Tenant::where('created_at', '>=', $agora->copy()->subDays(7))->count(),
                'mes' => $novos30,
                'crescimento' => $novos30Antes > 0 ? round(($novos30 - $novos30Antes) / $novos30Antes * 100, 1) : ($novos30 > 0 ? 100 : 0),
            ],
            'utilizadores' => [
                'total' => User::where('is_super_admin', false)->count(),
                'novos_hoje' => User::whereDate('created_at', $hoje)->count(),
                'activos_hoje' => User::whereDate('last_login_at', $hoje)->count(),
            ],
            'receita' => $this->receita($agora),
            'visitas' => $this->visitas($agora),
            'pedidos_pendentes' => Order::where('status', 'pending')->count(),
            'empresas_recentes' => Tenant::orderByDesc('created_at')->limit(8)->get(['id', 'name', 'slug', 'is_active', 'created_at'])
                ->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name, 'slug' => $t->slug, 'activa' => (bool) $t->is_active, 'criada' => $t->created_at?->toIso8601String()])
                ->values()->all(),
            'serie' => $this->serie(),
            'sistema' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'ambiente' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'fuso' => config('app.timezone'),
                'base_de_dados' => config('database.default'),
                'cache' => config('cache.default'),
                'fila' => config('queue.default'),
            ],
        ];
    }

    private function receita(Carbon $agora): array
    {
        return [
            'mrr' => round((float) DB::table('subscriptions')
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->where('subscriptions.status', 'active')
                ->sum('plans.price_monthly'), 2),
            'paga_no_mes' => round((float) Invoice::where('status', 'paid')
                ->whereMonth('paid_at', $agora->month)->whereYear('paid_at', $agora->year)->sum('total'), 2),
        ];
    }

    private function visitas(Carbon $agora): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return ['disponivel' => false, 'visitantes' => 0, 'paginas_vistas' => 0, 'cliques_registo' => 0, 'paginas' => [], 'origens' => [], 'leads' => []];
        }

        $desde = $agora->copy()->subDays(30);
        $base = fn () => AnalyticsEvent::where('created_at', '>=', $desde);

        return [
            'disponivel' => true,
            'visitantes' => $base()->distinct('visitor_id')->count('visitor_id'),
            'paginas_vistas' => $base()->where('type', 'pageview')->count(),
            'cliques_registo' => $base()->where('event_name', 'click_register')->count(),
            'paginas' => $base()->where('type', 'pageview')
                ->select('path', DB::raw('COUNT(*) as vistas'))
                ->groupBy('path')->orderByDesc('vistas')->limit(5)->get()
                ->map(fn ($p) => ['caminho' => $p->path ?: '/', 'vistas' => (int) $p->vistas])->values()->all(),
            'origens' => $base()->where('type', 'pageview')
                ->select(DB::raw("COALESCE(utm_source, CASE
                    WHEN referrer = '' OR referrer IS NULL THEN 'direct'
                    WHEN referrer LIKE '%google%' THEN 'google'
                    WHEN referrer LIKE '%facebook%' THEN 'facebook'
                    WHEN referrer LIKE '%whatsapp%' THEN 'whatsapp'
                    ELSE 'other' END) as origem"), DB::raw('COUNT(DISTINCT visitor_id) as visitantes'))
                ->groupBy('origem')->orderByDesc('visitantes')->limit(5)->get()
                ->map(fn ($o) => ['origem' => $o->origem, 'visitantes' => (int) $o->visitantes])->values()->all(),
            'leads' => $base()
                ->select('visitor_id',
                    DB::raw("SUM(CASE WHEN event_name='click_register' THEN 30 WHEN event_name='click_whatsapp' THEN 15 WHEN event_name='click_module' THEN 3 WHEN type='cta_click' THEN 5 ELSE 1 END) as pontos"),
                    DB::raw('MAX(country) as pais'),
                    DB::raw('MAX(device_type) as aparelho'),
                    DB::raw('MAX(utm_source) as origem'))
                ->groupBy('visitor_id')->orderByDesc('pontos')->limit(5)->get()
                ->map(fn ($l) => ['visitante' => substr((string) $l->visitor_id, 0, 12), 'pontos' => (int) $l->pontos, 'pais' => $l->pais, 'aparelho' => $l->aparelho, 'origem' => $l->origem])
                ->values()->all(),
        ];
    }

    /** Novas empresas e novos utilizadores por dia, nos últimos 14 dias — duas consultas, não 28. */
    private function serie(): array
    {
        $desde = Carbon::today()->subDays(13);
        $empresas = Tenant::where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as n')->groupBy('dia')->pluck('n', 'dia');
        $utilizadores = User::where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as n')->groupBy('dia')->pluck('n', 'dia');

        $serie = [];
        for ($i = 13; $i >= 0; $i--) {
            $dia = Carbon::today()->subDays($i)->toDateString();
            $serie[] = ['dia' => $dia, 'empresas' => (int) ($empresas[$dia] ?? 0), 'utilizadores' => (int) ($utilizadores[$dia] ?? 0)];
        }

        return $serie;
    }
}
