<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Api\Plataforma\AnalyticsApiController;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Tenants\SinaisDeVida;
use App\Support\CicloDeFacturacao;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RELATÓRIOS E ANALYTICS PARA O AGENTE — só leitura, escopo `analytics:read`.
 *
 * O agente via números soltos (`analytics/overview`) e não conseguia responder
 * a «como correu o mês?», «quanto entrou de subscrições?», «esta empresa vende
 * mais do que no mês passado?» nem ver o analytics do site — que existia no
 * painel do dono e não tinha porta nenhuma para ele.
 *
 *  · `reports/plataforma` — o negócio da plataforma, mês a mês;
 *  · `reports/documentos` — o que as empresas emitem, por dia e por empresa;
 *  · `reports/tenants/{id}/vendas` — as vendas de uma empresa por mês e tipo;
 *  · `analytics/site` — o MESMO painel de analytics do dono (mesmos filtros e
 *    as mesmas contas: é o controlador dele que responde, não uma cópia);
 *  · `analytics/uso` — quem usa o ERP e que ecrãs.
 *
 * Os documentos em rascunho não contam em nada disto: não foram emitidos.
 */
class RelatoriosController extends Controller
{
    /* ══════════════ A plataforma ══════════════ */

    public function plataforma(Request $request)
    {
        $d = $request->validate(['meses' => 'nullable|integer|min:1|max:36']);
        $meses = (int) ($d['meses'] ?? 12);
        $desde = now()->startOfMonth()->subMonths($meses - 1);

        $mes = fn (string $coluna) => DB::raw("DATE_FORMAT({$coluna}, '%Y-%m') AS mes");

        $novas = DB::table('tenants')->whereNull('deleted_at')->where('created_at', '>=', $desde)
            ->select($mes('created_at'), DB::raw('COUNT(*) AS n'))->groupBy('mes')->pluck('n', 'mes');
        $desactivadas = Schema::hasColumn('tenants', 'deactivated_at')
            ? DB::table('tenants')->whereNull('deleted_at')->where('is_active', false)->where('deactivated_at', '>=', $desde)
                ->select($mes('deactivated_at'), DB::raw('COUNT(*) AS n'))->groupBy('mes')->pluck('n', 'mes')
            : collect();
        $pedidos = DB::table('orders')->where('status', 'approved')->where('approved_at', '>=', $desde)
            ->select($mes('approved_at'), DB::raw('COUNT(*) AS n'), DB::raw('SUM(amount) AS valor'))->groupBy('mes')->get()->keyBy('mes');
        $pagas = DB::table('invoices')->where('status', 'paid')->where('paid_at', '>=', $desde)
            ->select($mes('paid_at'), DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'))->groupBy('mes')->get()->keyBy('mes');

        $porMes = collect(range(0, $meses - 1))->map(function ($i) use ($desde, $novas, $desactivadas, $pedidos, $pagas) {
            $m = $desde->copy()->addMonths($i)->format('Y-m');

            return [
                'mes' => $m,
                'empresas_novas' => (int) ($novas[$m] ?? 0),
                'empresas_desactivadas' => (int) ($desactivadas[$m] ?? 0),
                'pedidos_aprovados' => (int) ($pedidos[$m]->n ?? 0),
                'valor_dos_pedidos' => round((float) ($pedidos[$m]->valor ?? 0), 2),
                'facturas_da_plataforma_pagas' => (int) ($pagas[$m]->n ?? 0),
                'recebido' => round((float) ($pagas[$m]->valor ?? 0), 2),
            ];
        });

        // SUBSCRIÇÕES VIVAS e o que valem por mês. Uma anual de 120 000 não
        // são 120 000 por mês: divide-se pelos meses que paga (sem a oferta,
        // que é tempo dado e não dinheiro).
        $subs = Subscription::with('plan:id,name')
            ->whereIn('status', ['active', 'trial'])->whereNull('cancelled_at')
            ->get(['id', 'tenant_id', 'plan_id', 'status', 'billing_cycle', 'amount', 'trial_ends_at', 'current_period_end']);
        $pagantes = $subs->filter(fn ($s) => $s->status === 'active' && (float) $s->amount > 0
            && ! ($s->trial_ends_at && $s->trial_ends_at->isFuture()));
        $mensal = fn ($s) => (float) $s->amount / max(1, CicloDeFacturacao::meses($s->billing_cycle, false));

        $sinais = SinaisDeVida::para(Tenant::query()->pluck('id'));

        return response()->json([
            'meses' => $meses,
            'por_mes' => $porMes,
            'subscricoes' => [
                'activas' => $subs->where('status', 'active')->count(),
                'em_teste' => $subs->filter(fn ($s) => $s->status === 'trial' || ($s->trial_ends_at && $s->trial_ends_at->isFuture()))->count(),
                'pagantes' => $pagantes->count(),
                'receita_mensal_recorrente' => round($pagantes->sum($mensal), 2),
                'por_plano' => $subs->groupBy(fn ($s) => $s->plan?->name ?? 'Sem plano')->map(fn (Collection $g, $plano) => [
                    'plano' => $plano,
                    'subscricoes' => $g->count(),
                    'pagantes' => $g->filter(fn ($s) => $pagantes->contains('id', $s->id))->count(),
                    'receita_mensal' => round($g->filter(fn ($s) => $pagantes->contains('id', $s->id))->sum($mensal), 2),
                ])->sortByDesc('receita_mensal')->values(),
                'por_ciclo' => $pagantes->groupBy(fn ($s) => CicloDeFacturacao::nome($s->billing_cycle))->map->count(),
            ],
            'testes_a_acabar_7d' => $subs->filter(fn ($s) => $s->trial_ends_at && $s->trial_ends_at->between(now(), now()->addDays(7)))
                ->map(fn ($s) => ['tenant_id' => $s->tenant_id, 'plano' => $s->plan?->name, 'acaba' => $s->trial_ends_at->toDateString()])->values(),
            'periodos_a_acabar_7d' => $subs->filter(fn ($s) => $s->status === 'active' && $s->current_period_end && $s->current_period_end->between(now(), now()->addDays(7)))
                ->map(fn ($s) => ['tenant_id' => $s->tenant_id, 'plano' => $s->plan?->name, 'acaba' => $s->current_period_end->toDateString(), 'valor' => (float) $s->amount])->values(),
            'empresas_por_estado' => $sinais->groupBy(fn ($s) => $s->estado['chave'])->map->count(),
            'nota' => 'receita_mensal_recorrente = subscrições activas pagas (fora do teste), divididas pelos meses que o ciclo paga.',
        ]);
    }

    /** Documentos de venda emitidos em toda a plataforma. */
    public function documentos(Request $request)
    {
        $d = $request->validate(['dias' => 'nullable|integer|min:1|max:365']);
        $dias = (int) ($d['dias'] ?? 30);
        $desde = now()->subDays($dias - 1)->startOfDay();

        $base = fn () => DB::table('invoicing_sales_invoices')->whereNull('deleted_at')
            ->where('status', '<>', 'draft')->where('created_at', '>=', $desde);

        $porDia = $base()->select(DB::raw('DATE(created_at) AS dia'), DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'),
                DB::raw('COUNT(DISTINCT tenant_id) AS empresas'))
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($l) => ['dia' => $l->dia, 'documentos' => (int) $l->n, 'valor' => round((float) $l->valor, 2), 'empresas' => (int) $l->empresas]);

        $porEmpresa = $base()->select('tenant_id', DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'), DB::raw('MAX(created_at) AS ultimo'))
            ->groupBy('tenant_id')->orderByDesc('n')->limit(30)->get();
        $nomes = Tenant::withTrashed()->whereIn('id', $porEmpresa->pluck('tenant_id'))->pluck('name', 'id');

        return response()->json([
            'dias' => $dias,
            'totais' => [
                'documentos' => (int) $porDia->sum('documentos'),
                'valor' => round($porDia->sum('valor'), 2),
                'empresas_que_emitiram' => $base()->distinct()->count('tenant_id'),
            ],
            'por_tipo' => $base()->select('invoice_type', DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'))->groupBy('invoice_type')->get()
                ->map(fn ($l) => ['tipo' => $l->invoice_type, 'documentos' => (int) $l->n, 'valor' => round((float) $l->valor, 2)]),
            'por_dia' => $porDia,
            'por_empresa' => $porEmpresa->map(fn ($l) => [
                'tenant_id' => (int) $l->tenant_id, 'empresa' => $nomes[$l->tenant_id] ?? null,
                'documentos' => (int) $l->n, 'valor' => round((float) $l->valor, 2), 'ultimo' => $l->ultimo,
            ]),
        ]);
    }

    /** As vendas de uma empresa por mês, tipo, estado AGT e forma de pagamento. */
    public function vendasDaEmpresa(Request $request, Tenant $tenant)
    {
        $d = $request->validate(['meses' => 'nullable|integer|min:1|max:36']);
        $meses = (int) ($d['meses'] ?? 12);
        $desde = now()->startOfMonth()->subMonths($meses - 1);

        $base = fn () => DB::table('invoicing_sales_invoices')->where('tenant_id', $tenant->id)->whereNull('deleted_at')
            ->where('status', '<>', 'draft')->where('created_at', '>=', $desde);

        $linhas = $base()->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') AS mes"), 'invoice_type', DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'))
            ->groupBy('mes', 'invoice_type')->get()->groupBy('mes');

        $notas = DB::table('invoicing_credit_notes')->where('tenant_id', $tenant->id)->whereNull('deleted_at')
            ->where('created_at', '>=', $desde)->where('status', '<>', 'draft')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') AS mes"), DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'))
            ->groupBy('mes')->get()->keyBy('mes');

        $porMes = collect(range(0, $meses - 1))->map(function ($i) use ($desde, $linhas, $notas) {
            $m = $desde->copy()->addMonths($i)->format('Y-m');
            $doMes = collect($linhas[$m] ?? []);

            return [
                'mes' => $m,
                'documentos' => (int) $doMes->sum('n'),
                'valor' => round((float) $doMes->sum('valor'), 2),
                'por_tipo' => $doMes->mapWithKeys(fn ($l) => [$l->invoice_type => ['documentos' => (int) $l->n, 'valor' => round((float) $l->valor, 2)]]),
                'notas_de_credito' => (int) ($notas[$m]->n ?? 0),
                'valor_creditado' => round((float) ($notas[$m]->valor ?? 0), 2),
            ];
        });

        $total = (int) $porMes->sum('documentos');

        return response()->json([
            'empresa' => ['id' => $tenant->id, 'nome' => $tenant->name],
            'meses' => $meses,
            'totais' => [
                'documentos' => $total,
                'valor' => round($porMes->sum('valor'), 2),
                'valor_medio' => $total > 0 ? round($porMes->sum('valor') / $total, 2) : 0,
                'creditado' => round($porMes->sum('valor_creditado'), 2),
                'ultimo_documento' => DB::table('invoicing_sales_invoices')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->where('status', '<>', 'draft')->max('created_at'),
            ],
            'por_mes' => $porMes,
            'por_estado' => $base()->select('status', DB::raw('COUNT(*) AS n'))->groupBy('status')->pluck('n', 'status'),
            'agt' => $base()->select(DB::raw("COALESCE(agt_status, 'sem_estado') AS estado"), DB::raw('COUNT(*) AS n'))->groupBy('estado')->pluck('n', 'estado'),
            'formas_de_pagamento' => $base()->select(DB::raw("COALESCE(payment_method, '—') AS forma"), DB::raw('COUNT(*) AS n'), DB::raw('SUM(total) AS valor'))
                ->groupBy('forma')->orderByDesc('n')->get()
                ->map(fn ($l) => ['forma' => $l->forma, 'documentos' => (int) $l->n, 'valor' => round((float) $l->valor, 2)]),
        ]);
    }

    /* ══════════════ Analytics ══════════════ */

    /**
     * O analytics do site público — o MESMO que o dono vê no painel.
     *
     * Responde o controlador do painel, com os mesmos filtros (periodo, de,
     * ate, aparelho, canal, pais, browser, pagina): duas contas diferentes para
     * a mesma pergunta davam duas respostas diferentes.
     */
    public function site(Request $request)
    {
        return app(AnalyticsApiController::class)->index($request);
    }

    public function visitante(string $visitante)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $visitante) === 1, 404);

        return app(AnalyticsApiController::class)->percurso($visitante);
    }

    /**
     * Quem usa o ERP, e o quê.
     *
     * Os eventos com `user_id` são clientes a trabalhar. Os caminhos levam ids
     * (`/invoicing/sales/invoices/123`): trocam-se por {id} para que o mesmo
     * ecrã conte como um só.
     */
    public function uso(Request $request)
    {
        $d = $request->validate([
            'dias' => 'nullable|integer|min:1|max:90',
            'tenant_id' => 'nullable|integer',
        ]);
        $dias = (int) ($d['dias'] ?? 7);
        $desde = now()->subDays($dias);

        $base = fn () => DB::table('analytics_events')->whereNotNull('user_id')->where('created_at', '>=', $desde)
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('tenant_id', $d['tenant_id']));

        $porEmpresa = $base()->whereNotNull('tenant_id')
            ->select('tenant_id', DB::raw('COUNT(*) AS eventos'), DB::raw('COUNT(DISTINCT user_id) AS pessoas'),
                DB::raw('COUNT(DISTINCT path) AS paginas'), DB::raw('MAX(created_at) AS ultimo'))
            ->groupBy('tenant_id')->orderByDesc('eventos')->limit(50)->get();
        $nomes = Tenant::withTrashed()->whereIn('id', $porEmpresa->pluck('tenant_id'))->pluck('name', 'id');

        // Os caminhos agrupam-se em PHP: com ids pelo meio, o GROUP BY do SQL
        // contava cada factura como um ecrã diferente.
        $ecras = $base()->where('type', 'pageview')->select('path', DB::raw('COUNT(*) AS n'), DB::raw('COUNT(DISTINCT user_id) AS pessoas'))
            ->groupBy('path')->orderByDesc('n')->limit(2000)->get()
            ->groupBy(fn ($l) => preg_replace('#/\d+(?=/|$)#', '/{id}', (string) ($l->path ?: '/')))
            ->map(fn ($g, $ecra) => ['ecra' => $ecra, 'vistas' => (int) $g->sum('n'), 'pessoas_max' => (int) $g->max('pessoas')])
            ->sortByDesc('vistas')->take(30)->values();

        return response()->json([
            'dias' => $dias,
            'totais' => [
                'eventos' => $base()->count(),
                'pessoas' => $base()->distinct()->count('user_id'),
                'empresas' => $base()->whereNotNull('tenant_id')->distinct()->count('tenant_id'),
            ],
            'por_empresa' => $porEmpresa->map(fn ($l) => [
                'tenant_id' => (int) $l->tenant_id, 'empresa' => $nomes[$l->tenant_id] ?? null,
                'eventos' => (int) $l->eventos, 'pessoas' => (int) $l->pessoas, 'paginas_distintas' => (int) $l->paginas, 'ultimo' => $l->ultimo,
            ]),
            'ecras_mais_usados' => $ecras,
        ]);
    }
}
