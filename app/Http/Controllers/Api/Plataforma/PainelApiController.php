<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DA PLATAFORMA — quantas empresas há, quanto entra e o que expira.
 *
 * NÃO É UM ECRÃ DE EMPRESA: aqui não há `tenant_id` nenhum no escopo, porque
 * quem olha é o dono da plataforma e o que conta são TODAS as empresas. É por
 * isso que estas rotas vivem noutro grupo (`api/v1/plataforma`), atrás do
 * `superadmin` e sem o `subscription` — o dono não é subscritor de nada.
 *
 * O QUE MUDOU DE SUBSTÂNCIA: os `catch (\Exception $e) {}` VAZIOS.
 * O componente antigo embrulhava cada consulta de dinheiro num try/catch sem
 * corpo, pelo que uma falha na consulta mostrava RECEITA ZERO — indistinguível
 * de um mês sem vendas. Aqui as consultas correm; se alguma falhar, falha à
 * vista, que é o que permite consertá-la.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $totais = $this->totais();

        return response()->json([
            'numeros' => $totais,
            'crescimento_das_empresas' => $this->crescimentoDasEmpresas(),
            'receita_mensal' => $this->receitaMensal(),
            'planos' => $this->porPlano(),
            'modulos' => $this->modulosMaisUsados(),
            'maiores_subscricoes' => $this->maioresSubscricoes(),
            'a_expirar' => $this->aExpirar(),
            'empresas_recentes' => $this->empresasRecentes(),
            'pedidos_recentes' => $this->pedidosRecentes(),
            'facturas_recentes' => $this->facturasRecentes(),
        ]);
    }

    /** @return array<string, int|float> */
    private function totais(): array
    {
        $empresas = Tenant::count();
        $activas = Tenant::where('is_active', true)->count();

        $esteMes = Tenant::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $mesPassado = Tenant::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)->count();

        return [
            'empresas' => $empresas,
            'activas' => $activas,
            'inactivas' => $empresas - $activas,
            // O dono da plataforma não se conta a si próprio.
            'utilizadores' => User::where('is_super_admin', false)->count(),
            'modulos' => Module::where('is_active', true)->count(),
            'subscricoes_activas' => Subscription::where('status', 'active')->count(),
            'subscricoes_em_ensaio' => Subscription::where('status', 'trial')->count(),
            'receita_total' => round((float) Invoice::where('status', 'paid')->sum('total'), 2),
            'receita_do_mes' => round((float) Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)->sum('total'), 2),
            'receita_por_cobrar' => round((float) Invoice::where('status', 'pending')->sum('total'), 2),
            'pedidos_por_aprovar' => Order::where('status', 'pending')->count(),
            'empresas_novas_do_mes' => $esteMes,
            /*
             * O CRESCIMENTO contra o mês passado. Sem mês passado não há
             * percentagem — e «100%» sobre zero é uma frase sem sentido, por
             * isso vai `null` e o ecrã diz «primeiro mês».
             */
            'crescimento' => $mesPassado > 0
                ? round((($esteMes - $mesPassado) / $mesPassado) * 100, 1)
                : null,
        ];
    }

    /** Empresas novas por mês, nos últimos seis. */
    private function crescimentoDasEmpresas(): array
    {
        $porMes = DB::table('tenants')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as quantas")
            ->pluck('quantas', 'mes');

        return $this->seisMeses(fn ($chave) => (int) ($porMes[$chave] ?? 0));
    }

    /** Receita cobrada por mês, nos últimos seis. */
    private function receitaMensal(): array
    {
        $porMes = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as mes, SUM(total) as valor")
            ->pluck('valor', 'mes');

        return $this->seisMeses(fn ($chave) => round((float) ($porMes[$chave] ?? 0), 2));
    }

    /**
     * Seis meses, e os vazios a zero.
     *
     * Um gráfico que salta os meses sem dados comprime o tempo e mente sobre a
     * tendência — é a mesma regra do painel da contabilidade.
     */
    private function seisMeses(callable $valor): array
    {
        $etiquetas = [];
        $valores = [];

        for ($i = 5; $i >= 0; $i--) {
            $dia = now()->subMonths($i);

            $etiquetas[] = $dia->translatedFormat('M/y');
            $valores[] = $valor($dia->format('Y-m'));
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    private function porPlano(): array
    {
        return Plan::where('is_active', true)
            ->withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('subscriptions_count')
            ->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'empresas' => (int) $p->subscriptions_count,
                'preco' => round((float) $p->price_monthly, 2),
                'promocional' => (bool) $p->is_promotional,
            ])->values()->all();
    }

    private function modulosMaisUsados(): array
    {
        return Module::where('is_active', true)
            /*
             * `wherePivot` NÃO SERVE AQUI. Dentro de um `withCount` o que chega
             * ao fecho é o construtor da subconsulta, não a relação — e o
             * `wherePivot` saía como `and pivot = is_active`, coluna que não
             * existe: a consulta rebentava e o painel inteiro com ela. A tabela
             * de ligação nomeia-se por inteiro.
             */
            ->withCount(['tenants' => fn ($q) => $q->where('tenant_module.is_active', true)])
            ->orderByDesc('tenants_count')
            ->take(10)->get()
            ->map(fn (Module $m) => [
                'id' => $m->id,
                'nome' => $m->name,
                'icone' => $m->icon ?: 'fas fa-puzzle-piece',
                'empresas' => (int) $m->tenants_count,
                // Um módulo `core` está em todas por omissão: a contagem alta
                // dele não diz nada sobre o que se vende.
                'nucleo' => (bool) $m->is_core,
            ])->values()->all();
    }

    private function maioresSubscricoes(): array
    {
        return Subscription::with(['tenant:id,name', 'plan:id,name'])
            ->where('status', 'active')
            ->orderByDesc('amount')
            ->take(5)->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'empresa' => $s->tenant?->name,
                'empresa_id' => $s->tenant_id,
                'plano' => $s->plan?->name,
                'valor' => round((float) $s->amount, 2),
                'ciclo' => $s->billing_cycle,
            ])->values()->all();
    }

    /** As que acabam nos próximos trinta dias — é quem se liga a tempo. */
    private function aExpirar(): array
    {
        return Subscription::with(['tenant:id,name', 'plan:id,name'])
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), now()->addDays(30)])
            ->orderBy('current_period_end')
            ->take(10)->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'empresa' => $s->tenant?->name,
                'empresa_id' => $s->tenant_id,
                'plano' => $s->plan?->name,
                'dia' => $s->current_period_end?->format('Y-m-d'),
                // Quantos dias faltam: é isto que decide a quem se telefona hoje.
                'dias' => $s->current_period_end
                    ? (int) now()->startOfDay()->diffInDays($s->current_period_end->startOfDay(), false)
                    : null,
                'valor' => round((float) $s->amount, 2),
            ])->values()->all();
    }

    private function empresasRecentes(): array
    {
        return Tenant::with(['activeSubscription.plan:id,name'])
            ->withCount('users')
            ->latest()->take(5)->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'nome' => $t->name,
                'email' => $t->email,
                'activa' => (bool) $t->is_active,
                'utilizadores' => (int) $t->users_count,
                'plano' => $t->activeSubscription?->plan?->name,
                'criada_em' => $t->created_at?->format('Y-m-d'),
            ])->values()->all();
    }

    private function pedidosRecentes(): array
    {
        return Order::with(['tenant:id,name', 'plan:id,name'])
            ->latest()->take(5)->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'empresa' => $o->tenant?->name,
                'plano' => $o->plan?->name,
                'valor' => round((float) $o->amount, 2),
                'estado' => $o->status,
                'dia' => $o->created_at?->format('Y-m-d'),
            ])->values()->all();
    }

    private function facturasRecentes(): array
    {
        return Invoice::with(['tenant:id,name'])
            ->latest()->take(5)->get()
            ->map(fn (Invoice $f) => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'empresa' => $f->tenant?->name,
                'valor' => round((float) $f->total, 2),
                'estado' => $f->status,
                'dia' => $f->invoice_date?->format('Y-m-d'),
            ])->values()->all();
    }

    /**
     * ENTRAR NA CASA DE UMA EMPRESA.
     *
     * É o acto com mais poder que o sistema tem: daqui para a frente tudo o que
     * se fizer aparece como sendo dentro daquela empresa. Fica na trilha de
     * auditoria — as linhas seguintes já levavam o `impersonator_id`, o que
     * faltava era o MOMENTO DA ENTRADA, sem o qual a trilha mostra actos sem
     * mostrar quem abriu a porta.
     */
    public function entrarNaEmpresa(Request $request, int $id): JsonResponse
    {
        $empresa = Tenant::findOrFail($id);

        app(\App\Services\Audit\AuditRecorder::class)->acto(
            'personificacao.entrou',
            $empresa->id,
            ['empresa' => $empresa->name],
            $empresa,
        );

        session(['impersonate_tenant_id' => $empresa->id]);

        return response()->json([
            'message' => __('A entrar em :empresa.', ['empresa' => $empresa->name]),
            'seguir_para' => '/dashboard',
        ]);
    }
}
