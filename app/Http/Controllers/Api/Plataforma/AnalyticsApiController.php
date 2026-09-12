<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Services\Analytics\Origem;
use App\Services\Analytics\Regiao;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * QUEM NOS DESCOBRIU, POR ONDE, E O QUE FOI VER.
 *
 * As consultas são as do componente em Livewire, que já tinham sido trabalhadas
 * com cuidado — e os comentários que explicam PORQUE são assim vêm com elas:
 *
 *  · QUEM TEM SESSÃO NÃO É VISITANTE. Um cliente a trabalhar no ERP inflava os
 *    visitantes, as sessões e as páginas vistas, e aparecia classificado como
 *    tráfego «directo» que na verdade é gente que já paga. Tem painel próprio.
 *  · O CANAL DERIVA-SE DO DOMÍNIO do referrer, e um domínio não se extrai com
 *    LIKE: classifica-se em PHP, sobre o conjunto já reduzido pelos filtros.
 *  · O CANAL DE UM VISITANTE é por onde ele ENTROU, não por onde andou depois:
 *    classifica-se a PRIMEIRA visita de cada um.
 *  · OS NOVE NÚMEROS numa consulta só, com agregados condicionais — eram nove
 *    varreduras da mesma tabela para nove perguntas que cabem numa linha.
 *  · E A SÉRIE TEMPORAL num `group by` — aqui esteve um ciclo que fazia uma
 *    consulta POR DIA do período, trinta e uma para desenhar um gráfico.
 *
 * O que o ecrã em Blade calculava a meio do HTML — a bandeira e o nome do país,
 * o canal de entrada de um visitante, o ícone de cada canal — sai agora daqui.
 * A bandeira e o nome vêm do ICU, que só existe do lado do servidor.
 */
class AnalyticsApiController extends Controller
{
    /** Quem está no site AGORA: visto nos últimos cinco minutos. */
    private const MINUTOS_ONLINE = 5;

    /** @var array<string, mixed> */
    private array $filtros = [];

    public function index(Request $request): JsonResponse
    {
        $this->filtros = $request->validate([
            'periodo' => ['nullable', Rule::in(['today', '7d', '30d', '90d', 'all', 'custom'])],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'aparelho' => ['nullable', 'string', 'max:40'],
            'canal' => ['nullable', Rule::in(Origem::CANAIS)],
            'pais' => ['nullable', 'string', 'max:80'],
            'browser' => ['nullable', 'string', 'max:60'],
            'pagina' => ['nullable', 'string', 'max:255'],
        ]);

        $de = $this->de();
        $ate = $this->ate();
        $proprio = parse_url(config('app.url'), PHP_URL_HOST);

        return response()->json([
            'periodo' => [
                'nome' => $this->filtros['periodo'] ?? '7d',
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
            ],
            'agora' => $this->agora(),
            'utilizadores' => $this->utilizadoresDentro(),
            'numeros' => $this->numeros($de, $ate),
            'paginas' => $this->paginas(),
            'origens' => $this->origens($proprio),
            'regiao' => $this->regiao(),
            'procuras' => $this->procuras(),
            'aparelhos' => $this->aparelhos(),
            'dias' => $this->porDia($de, $ate),
            'funil' => $this->funil(),
            'visitantes' => $this->visitantes(),
            'ao_vivo' => $this->aoVivo(),
            'opcoes' => $this->opcoes($de, $ate),
        ]);
    }

    /**
     * O percurso de um visitante: por onde entrou, por onde andou, onde parou.
     *
     * Morada própria e não um campo do painel: pedir o percurso de um visitante
     * não tem de recalcular os vinte agregados do ecrã todo.
     */
    public function percurso(string $visitante): JsonResponse
    {
        $eventos = AnalyticsEvent::where('visitor_id', $visitante)
            ->orderBy('created_at')->limit(200)->get();

        $primeiro = $eventos->first();
        $cabeca = null;

        if ($primeiro) {
            $origem = Origem::classificar(
                $primeiro->referrer,
                $primeiro->utm_source,
                parse_url(config('app.url'), PHP_URL_HOST)
            );

            $cabeca = [
                'chegou' => $primeiro->created_at?->format('d/m/Y H:i'),
                'canal' => $origem['canal'],
                'fonte' => $origem['fonte'],
                'bandeira' => Regiao::bandeira($primeiro->country),
                'onde' => $primeiro->city ?: Regiao::nomeDoPais($primeiro->country),
                'aparelho' => $primeiro->device_type,
                'browser' => $primeiro->browser,
            ];
        }

        return response()->json([
            'visitante' => $visitante,
            'cabeca' => $cabeca,
            'passos' => $eventos->map(fn ($e) => [
                'id' => $e->id,
                'quando' => $e->created_at?->format('d/m H:i:s'),
                'tipo' => $e->type,
                'nome' => $e->event_name,
                'pagina' => $e->path ?: '/',
                'termo' => $e->search_term,
                'segundos' => $e->duration_seconds === null ? null : (int) $e->duration_seconds,
            ])->values(),
        ]);
    }

    /* ─── O período ───────────────────────────────────────────────────── */

    private function de(): Carbon
    {
        if (($this->filtros['periodo'] ?? '') === 'custom' && ! empty($this->filtros['de'])) {
            return Carbon::parse($this->filtros['de'])->startOfDay();
        }

        return match ($this->filtros['periodo'] ?? '7d') {
            'today' => Carbon::today(),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            'all' => Carbon::now()->subYears(5),
            default => Carbon::now()->subDays(7),
        };
    }

    private function ate(): Carbon
    {
        if (($this->filtros['periodo'] ?? '') === 'custom' && ! empty($this->filtros['ate'])) {
            return Carbon::parse($this->filtros['ate'])->endOfDay();
        }

        return Carbon::now();
    }

    /**
     * A consulta com os filtros todos aplicados.
     *
     * O filtro de CANAL não se faz aqui: um canal deriva-se do domínio do
     * referrer, e um domínio não se extrai com LIKE.
     */
    private function base()
    {
        $q = AnalyticsEvent::whereBetween('created_at', [$this->de(), $this->ate()]);

        // QUEM TEM SESSÃO INICIADA NÃO É VISITANTE — tem painel próprio.
        $q->whereNull('user_id');

        foreach ([
            'aparelho' => 'device_type',
            'pais' => 'country',
            'browser' => 'browser',
            'pagina' => 'path',
        ] as $filtro => $coluna) {
            if (! empty($this->filtros[$filtro])) {
                $q->where($coluna, $this->filtros[$filtro]);
            }
        }

        if (! empty($this->filtros['canal'])) {
            $q->whereIn('visitor_id', $this->visitantesDoCanal());
        }

        return $q;
    }

    /**
     * Os visitantes cujo canal de ENTRADA é o filtrado.
     *
     * Alguém que chega pelo Facebook e navega no site não passa a «interno» à
     * segunda página.
     */
    private function visitantesDoCanal(): array
    {
        $primeiras = AnalyticsEvent::whereBetween('created_at', [$this->de(), $this->ate()])
            ->select('visitor_id', DB::raw('MIN(id) as primeiro'))
            ->groupBy('visitor_id')->pluck('primeiro');

        if ($primeiras->isEmpty()) {
            return [];
        }

        $proprio = parse_url(config('app.url'), PHP_URL_HOST);
        $canal = $this->filtros['canal'];

        return AnalyticsEvent::whereIn('id', $primeiras)
            ->select('visitor_id', 'referrer', 'utm_source')->get()
            ->filter(fn ($e) => Origem::classificar($e->referrer, $e->utm_source, $proprio)['canal'] === $canal)
            ->pluck('visitor_id')->all();
    }

    /* ─── Quem está aqui agora ────────────────────────────────────────── */

    /** Sem período nem filtros: «agora» é agora. */
    private function agora(): array
    {
        $desde = Carbon::now()->subMinutes(self::MINUTOS_ONLINE);

        return [
            'visitantes' => AnalyticsEvent::where('created_at', '>=', $desde)
                ->whereNull('user_id')->distinct()->count('visitor_id'),
            'paginas' => AnalyticsEvent::where('created_at', '>=', $desde)
                ->whereNull('user_id')->where('type', 'pageview')
                ->select('path', DB::raw('COUNT(DISTINCT visitor_id) as visitantes'))
                ->groupBy('path')->orderByDesc('visitantes')->limit(6)->get()
                ->map(fn ($p) => ['pagina' => $p->path ?: '/', 'visitantes' => (int) $p->visitantes])->values(),
        ];
    }

    /**
     * QUEM ESTÁ DENTRO DO SISTEMA.
     *
     * Não são visitantes: são clientes a trabalhar. O ecrã antigo contava-os
     * como tráfego anónimo e eles desapareciam no meio de quem passa pelo site.
     * Aqui aparecem com nome, empresa, o que estão a ver e há quanto tempo.
     */
    private function utilizadoresDentro(): array
    {
        $agoraDesde = Carbon::now()->subMinutes(self::MINUTOS_ONLINE);

        $ultimos = AnalyticsEvent::whereNotNull('user_id')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->select('user_id', DB::raw('MAX(id) as ultimo'), DB::raw('COUNT(*) as acessos'))
            ->groupBy('user_id')->orderByDesc('ultimo')->limit(50)->get();

        if ($ultimos->isEmpty()) {
            return ['online' => 0, 'lista' => []];
        }

        $acessos = $ultimos->pluck('acessos', 'user_id');

        $lista = AnalyticsEvent::with(['user:id,name,email,tenant_id', 'user.tenant:id,name', 'tenant:id,name'])
            ->whereIn('id', $ultimos->pluck('ultimo'))
            ->orderByDesc('created_at')->get()
            ->map(fn ($e) => [
                'nome' => $e->user?->name ?? __('Utilizador #:id', ['id' => $e->user_id]),
                'email' => $e->user?->email,
                'empresa' => $e->tenant?->name ?? $e->user?->tenant?->name,
                'pagina' => $e->path ?: '/',
                'ha_quanto' => $e->created_at?->diffForHumans(short: true),
                'agora' => $e->created_at >= $agoraDesde,
                'acessos' => (int) ($acessos[$e->user_id] ?? 0),
                'aparelho' => $e->device_type,
                'cidade' => $e->city,
            ])->values();

        return [
            'online' => $lista->where('agora', true)->count(),
            'lista' => $lista,
        ];
    }

    /* ─── Os números ──────────────────────────────────────────────────── */

    private function numeros(Carbon $de, Carbon $ate): array
    {
        // OS NOVE NUMA CONSULTA SÓ, com agregados condicionais.
        $k = (clone $this->base())
            ->selectRaw('
                COUNT(DISTINCT visitor_id)                                     as visitantes,
                COUNT(DISTINCT session_id)                                     as sessoes,
                SUM(CASE WHEN type = "pageview"  THEN 1 ELSE 0 END)            as pageviews,
                SUM(CASE WHEN type = "cta_click" THEN 1 ELSE 0 END)            as cliques,
                SUM(CASE WHEN type = "search"    THEN 1 ELSE 0 END)            as pesquisas,
                SUM(CASE WHEN event_name = "click_register" THEN 1 ELSE 0 END) as registos,
                SUM(CASE WHEN event_name = "click_whatsapp" THEN 1 ELSE 0 END) as whatsapp,
                SUM(CASE WHEN event_name = "click_module"   THEN 1 ELSE 0 END) as modulos,
                COUNT(*)                                                       as eventos
            ')->first();

        $visitantes = (int) $k->visitantes;
        $sessoes = (int) $k->sessoes;
        $pageviews = (int) $k->pageviews;
        $registos = (int) $k->registos;

        // SESSÕES DE UMA SÓ PÁGINA: é a taxa de rejeição.
        $umaPagina = DB::query()->fromSub(
            (clone $this->base())->where('type', 'pageview')
                ->select('session_id', DB::raw('COUNT(*) as n'))->groupBy('session_id'),
            'p'
        )->where('n', 1)->count();

        // A COMPARAÇÃO com o período anterior, do mesmo tamanho. E com a mesma
        // regra: sem sessão iniciada. Contar TODOS no período anterior fazia
        // com que a tendência descesse sempre, só porque o de trás incluía os
        // clientes a trabalhar e o de agora não.
        $duracao = $de->diffInSeconds($ate);
        $antes = AnalyticsEvent::whereBetween('created_at', [$de->copy()->subSeconds($duracao), $de])
            ->whereNull('user_id')->distinct()->count('visitor_id');

        return [
            'visitantes' => $visitantes,
            'sessoes' => $sessoes,
            'pageviews' => $pageviews,
            'cliques' => (int) $k->cliques,
            'pesquisas' => (int) $k->pesquisas,
            'registos' => $registos,
            'whatsapp' => (int) $k->whatsapp,
            'modulos' => (int) $k->modulos,
            'eventos' => (int) $k->eventos,
            'conversao' => $visitantes > 0 ? round($registos / $visitantes * 100, 2) : 0,
            // PÁGINAS POR SESSÃO: mede se as pessoas navegam ou saem à primeira.
            'paginas_por_sessao' => $sessoes > 0 ? round($pageviews / $sessoes, 1) : 0,
            'rejeicao' => $sessoes > 0 ? (int) round($umaPagina / $sessoes * 100) : 0,
            // Sem período anterior não há tendência — e zero não é «igual».
            'tendencia' => $antes > 0 ? round(($visitantes - $antes) / $antes * 100, 1) : null,
        ];
    }

    private function paginas(): array
    {
        return (clone $this->base())
            ->where('type', 'pageview')
            ->select('path',
                DB::raw('COUNT(*) as vistas'),
                DB::raw('COUNT(DISTINCT visitor_id) as visitantes'),
                DB::raw('AVG(duration_seconds) as tempo_medio'))
            ->groupBy('path')->orderByDesc('vistas')->limit(15)->get()
            ->map(fn ($p) => [
                'pagina' => $p->path ?: '/',
                'vistas' => (int) $p->vistas,
                'visitantes' => (int) $p->visitantes,
                'tempo_medio' => $p->tempo_medio === null ? null : (int) round((float) $p->tempo_medio),
            ])->values()->all();
    }

    /**
     * DE ONDE VIERAM — uma linha por visitante, a PRIMEIRA visita dele.
     *
     * O `CASE` em SQL que aqui esteve juntava tudo o que não fosse
     * google/facebook/instagram num «other» que não dizia nada — e contava o
     * tráfego da própria aplicação como se viesse de fora.
     */
    private function origens(?string $proprio): array
    {
        $primeiras = (clone $this->base())
            ->select('visitor_id', DB::raw('MIN(id) as primeiro'))
            ->groupBy('visitor_id')->pluck('primeiro');

        if ($primeiras->isEmpty()) {
            return ['canais' => [], 'fontes' => []];
        }

        $classificadas = AnalyticsEvent::whereIn('id', $primeiras)
            ->select('referrer', 'utm_source')->get()
            ->map(fn ($e) => Origem::classificar($e->referrer, $e->utm_source, $proprio));

        return [
            'canais' => $classificadas->groupBy('canal')->map->count()->sortDesc()
                ->map(fn ($n, $canal) => [
                    'canal' => $canal,
                    'icone' => Origem::icone($canal),
                    'visitantes' => $n,
                ])->values(),

            'fontes' => $classificadas->groupBy('fonte')
                ->map(fn ($g, $fonte) => [
                    'fonte' => $fonte,
                    'canal' => $g->first()['canal'],
                    'icone' => Origem::icone($g->first()['canal']),
                    'visitantes' => $g->count(),
                ])
                ->sortByDesc('visitantes')->take(12)->values(),
        ];
    }

    private function regiao(): array
    {
        return [
            'paises' => (clone $this->base())
                ->select('country', DB::raw('COUNT(DISTINCT visitor_id) as quantos'))
                ->whereNotNull('country')->groupBy('country')
                ->orderByDesc('quantos')->limit(12)->get()
                ->map(fn ($p) => [
                    'codigo' => $p->country,
                    'nome' => Regiao::nomeDoPais($p->country),
                    'bandeira' => Regiao::bandeira($p->country),
                    'visitantes' => (int) $p->quantos,
                ])->values(),

            'cidades' => (clone $this->base())
                ->select('city', 'country', DB::raw('COUNT(DISTINCT visitor_id) as quantos'))
                ->whereNotNull('city')->where('city', '!=', 'Rede local')
                ->groupBy('city', 'country')->orderByDesc('quantos')->limit(12)->get()
                ->map(fn ($c) => [
                    'nome' => $c->city,
                    'bandeira' => Regiao::bandeira($c->country),
                    'visitantes' => (int) $c->quantos,
                ])->values(),

            // Quantos endereços ainda não têm região resolvida: explica por que
            // é que a soma das cidades não dá o total dos visitantes.
            'por_resolver' => Regiao::porResolver(),
        ];
    }

    private function procuras(): array
    {
        return (clone $this->base())
            ->where('type', 'search')->whereNotNull('search_term')
            ->select('search_term',
                DB::raw('COUNT(*) as vezes'),
                DB::raw('COUNT(DISTINCT visitor_id) as pessoas'),
                DB::raw('MAX(created_at) as ultima'))
            ->groupBy('search_term')->orderByDesc('vezes')->limit(20)->get()
            ->map(fn ($p) => [
                'termo' => $p->search_term,
                'vezes' => (int) $p->vezes,
                'pessoas' => (int) $p->pessoas,
                'ultima' => $p->ultima ? Carbon::parse($p->ultima)->diffForHumans() : null,
            ])->values()->all();
    }

    private function aparelhos(): array
    {
        $contar = fn (string $coluna, int $limite) => (clone $this->base())
            ->select($coluna, DB::raw('COUNT(DISTINCT visitor_id) as quantos'))
            ->when($coluna !== 'device_type', fn ($q) => $q->whereNotNull($coluna))
            ->groupBy($coluna)->orderByDesc('quantos')->limit($limite)->get()
            ->map(fn ($x) => ['nome' => $x->{$coluna} ?: __('Desconhecido'), 'visitantes' => (int) $x->quantos])
            ->values();

        return [
            'tipos' => $contar('device_type', 10),
            'browsers' => $contar('browser', 8),
            'sistemas' => $contar('os', 8),
        ];
    }

    /** A série temporal, num `group by` — e os dias vazios a zero. */
    private function porDia(Carbon $de, Carbon $ate): array
    {
        $porDia = (clone $this->base())
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(DISTINCT visitor_id) as visitantes'))
            ->groupBy('dia')->pluck('visitantes', 'dia');

        $etiquetas = [];
        $valores = [];

        $cursor = $de->copy()->startOfDay();
        $fim = $ate->copy()->startOfDay();
        $limite = 0;

        // O tecto de 92 dias é o que impede um «desde sempre» de desenhar mil
        // barras que ninguém lê.
        while ($cursor->lte($fim) && $limite++ < 92) {
            $etiquetas[] = $cursor->format('d/m');
            $valores[] = (int) ($porDia[$cursor->toDateString()] ?? 0);
            $cursor->addDay();
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** Os quatro degraus numa consulta, com `COUNT(DISTINCT ... CASE)`. */
    private function funil(): array
    {
        $f = (clone $this->base())
            ->selectRaw('
                COUNT(DISTINCT CASE WHEN path = "/"                    THEN visitor_id END) as entrada,
                COUNT(DISTINCT CASE WHEN path LIKE "/modulos%"         THEN visitor_id END) as modulos,
                COUNT(DISTINCT CASE WHEN event_name = "click_planos"   THEN visitor_id END) as planos,
                COUNT(DISTINCT CASE WHEN event_name = "click_register" THEN visitor_id END) as registo
            ')->first();

        return [
            ['degrau' => __('Chegou à página inicial'), 'quantos' => (int) $f->entrada, 'icone' => 'fa-house'],
            ['degrau' => __('Viu os módulos'), 'quantos' => (int) $f->modulos, 'icone' => 'fa-cubes'],
            ['degrau' => __('Clicou nos planos'), 'quantos' => (int) $f->planos, 'icone' => 'fa-tag'],
            ['degrau' => __('Começou o registo'), 'quantos' => (int) $f->registo, 'icone' => 'fa-rocket'],
        ];
    }

    /** Os visitantes por INTERESSE: quem vale um telefonema. */
    private function visitantes(): array
    {
        return (clone $this->base())
            ->select('visitor_id',
                DB::raw('COUNT(*) as eventos'),
                DB::raw("SUM(CASE WHEN type='pageview' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("SUM(CASE WHEN type='cta_click' THEN 1 ELSE 0 END) as cliques"),
                DB::raw("SUM(CASE WHEN event_name='click_register' THEN 30 WHEN event_name='click_whatsapp' THEN 15 WHEN event_name='click_module' THEN 3 WHEN type='cta_click' THEN 5 ELSE 1 END) as pontos"),
                DB::raw('MAX(created_at) as visto'),
                DB::raw('MIN(created_at) as primeiro'),
                DB::raw('MAX(country) as pais'),
                DB::raw('MAX(city) as cidade'),
                DB::raw('MAX(device_type) as aparelho'),
                DB::raw('MAX(browser) as browser'),
                DB::raw('MAX(utm_source) as utm'),
                DB::raw('MAX(referrer) as referrer'),
                DB::raw('COUNT(DISTINCT path) as paginas'),
                DB::raw('SUM(duration_seconds) as tempo'))
            ->groupBy('visitor_id')->orderByDesc('pontos')->limit(50)->get()
            ->map(fn ($v) => [
                'id' => $v->visitor_id,
                'pontos' => (int) $v->pontos,
                'eventos' => (int) $v->eventos,
                'pageviews' => (int) $v->pageviews,
                'cliques' => (int) $v->cliques,
                'paginas' => (int) $v->paginas,
                'tempo' => (int) $v->tempo,
                'bandeira' => Regiao::bandeira($v->pais),
                'pais' => $v->pais ? Regiao::nomeDoPais($v->pais) : null,
                'cidade' => $v->cidade,
                'aparelho' => $v->aparelho,
                'browser' => $v->browser,
                'utm' => $v->utm,
                // O domínio, sem o esquema nem o «www.»: é o que se lê na lista.
                'referrer' => $v->referrer ? Origem::dominio($v->referrer) : null,
                'primeiro' => $v->primeiro ? Carbon::parse($v->primeiro)->format('Y-m-d H:i') : null,
                'visto' => $v->visto ? Carbon::parse($v->visto)->diffForHumans() : null,
            ])->values()->all();
    }

    private function aoVivo(): array
    {
        return [
            'eventos' => (clone $this->base())
                ->orderByDesc('created_at')->limit(40)->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'ha_quanto' => $e->created_at?->diffForHumans(),
                    'tipo' => $e->type,
                    'nome' => $e->event_name,
                    'pagina' => $e->path,
                    'modulo' => is_array($e->meta) ? ($e->meta['module'] ?? null) : null,
                    'utm' => $e->utm_source,
                    'visitante' => $e->visitor_id,
                ])->values(),

            'por_acao' => (clone $this->base())
                ->where('type', 'cta_click')
                ->select('event_name', DB::raw('COUNT(*) as quantos'))
                ->groupBy('event_name')->orderByDesc('quantos')->limit(15)->get()
                ->map(fn ($e) => ['nome' => $e->event_name, 'quantos' => (int) $e->quantos])->values(),
        ];
    }

    /** As opções dos filtros, tiradas do que existe no período. */
    private function opcoes(Carbon $de, Carbon $ate): array
    {
        $noPeriodo = fn () => AnalyticsEvent::whereBetween('created_at', [$de, $ate])->whereNull('user_id');

        return [
            'paises' => $noPeriodo()->whereNotNull('country')->distinct()
                ->orderBy('country')->pluck('country')
                ->map(fn ($c) => [
                    'valor' => $c,
                    'rotulo' => trim(Regiao::bandeira($c).' '.Regiao::nomeDoPais($c)),
                ])->values(),

            'browsers' => $noPeriodo()->whereNotNull('browser')->distinct()
                ->orderBy('browser')->pluck('browser')
                ->map(fn ($b) => ['valor' => $b, 'rotulo' => $b])->values(),

            'paginas' => $noPeriodo()->where('type', 'pageview')->whereNotNull('path')
                ->distinct()->orderBy('path')->limit(60)->pluck('path')
                ->map(fn ($p) => ['valor' => $p, 'rotulo' => $p])->values(),

            // Os tipos de aparelho são uma lista fechada: a coluna guarda a
            // palavra em inglês, e é aqui que ela ganha o nome em português.
            'aparelhos' => [
                ['valor' => 'desktop', 'rotulo' => __('Computador')],
                ['valor' => 'mobile', 'rotulo' => __('Telemóvel')],
                ['valor' => 'tablet', 'rotulo' => __('Tablet')],
                ['valor' => 'bot', 'rotulo' => __('Robô')],
            ],

            'canais' => collect(Origem::CANAIS)->map(fn ($c) => [
                'valor' => $c,
                'rotulo' => ucfirst($c),
                'icone' => Origem::icone($c),
            ])->values(),

            'periodos' => [
                ['valor' => 'today', 'rotulo' => __('Hoje')],
                ['valor' => '7d', 'rotulo' => __('7 dias')],
                ['valor' => '30d', 'rotulo' => __('30 dias')],
                ['valor' => '90d', 'rotulo' => __('90 dias')],
                ['valor' => 'all', 'rotulo' => __('Desde sempre')],
                ['valor' => 'custom', 'rotulo' => __('Escolher datas')],
            ],
        ];
    }
}
