<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Move;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DA CONTABILIDADE.
 *
 * Mostrava «Total Ativo: 42» — quarenta e duas CONTAS de activo. Não é
 * informação nenhuma: um plano de contas tem as contas que tem, e o número não
 * muda com o negócio. O que um contabilista procura ao abrir isto é quanto há
 * em cada natureza, e isso vinha a lugar nenhum.
 *
 * SÓ OS LANÇAMENTOS CONFIRMADOS CONTAM. Um rascunho é uma intenção; somá-lo
 * dava um balanço que não existe em lado nenhum.
 */
class PainelApiController extends Controller
{
    /** As naturezas que crescem a débito. As outras crescem a crédito. */
    private const A_DEBITO = ['asset', 'expense'];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.dashboard.view');

        $tenantId = (int) activeTenantId();

        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $de = $filtros['de'] ?? now()->startOfMonth()->format('Y-m-d');
        $ate = $filtros['ate'] ?? now()->format('Y-m-d');

        $saldos = $this->saldosPorNatureza($tenantId, $de, $ate);

        $contas = fn () => Account::where('tenant_id', $tenantId)->where('blocked', false);
        $moves = fn () => Move::where('tenant_id', $tenantId);

        /*
         * OS VALORES SÃO PROTEGIDOS, e não é o ecrã que decide.
         *
         * O painel em Blade passava cada saldo por `valorProtegido(...,
         * 'accounting.reports.view')`: quem não pode ver relatórios via `•••`.
         * Aqui os números NÃO SAEM DO SERVIDOR quando não se podem ver — mandar
         * o valor e esconder no ecrã era guardar o segredo na resposta HTTP.
         */
        $veValores = podeVer('accounting.reports.view');

        $protegido = fn (float $v) => $veValores ? $v : null;

        return response()->json([
            'periodo' => ['de' => $de, 'ate' => $ate],
            've_valores' => $veValores,

            /*
             * O CABEÇALHO SÃO OS SALDOS, não as contagens.
             *
             * A natureza decide o sinal: activo e gasto crescem a débito;
             * passivo, capital e proveito crescem a crédito. Somar tudo da mesma
             * maneira dava proveitos negativos — e um painel que mostra a receita
             * com um sinal de menos não se usa duas vezes.
             */
            'saldos' => [
                'activo' => $protegido($saldos['asset'] ?? 0.0),
                'passivo' => $protegido($saldos['liability'] ?? 0.0),
                'capital' => $protegido($saldos['equity'] ?? 0.0),
                'proveitos' => $protegido($saldos['revenue'] ?? 0.0),
                'gastos' => $protegido($saldos['expense'] ?? 0.0),
                'resultado' => $protegido(($saldos['revenue'] ?? 0.0) - ($saldos['expense'] ?? 0.0)),
            ],

            // As contagens de contas ficam — servem para saber se o plano está
            // montado. Deixam é de ser o cabeçalho.
            'plano' => [
                'contas' => Account::where('tenant_id', $tenantId)->count(),
                'activo' => $contas()->where('type', 'asset')->count(),
                'passivo' => $contas()->where('type', 'liability')->count(),
                'capital' => $contas()->where('type', 'equity')->count(),
                'proveitos' => $contas()->where('type', 'revenue')->count(),
                'gastos' => $contas()->where('type', 'expense')->count(),
                // Uma conta bloqueada não recebe movimento: saber quantas há
                // explica por que é que alguém não a encontra ao lançar.
                'bloqueadas' => Account::where('tenant_id', $tenantId)->where('blocked', true)->count(),
                // E uma conta de AGREGAÇÃO soma as filhas: também não recebe.
                'agregacao' => Account::where('tenant_id', $tenantId)->where('is_view', true)->count(),
            ],

            'lancamentos' => [
                'total' => $moves()->count(),
                'rascunhos' => $moves()->where('state', 'draft')->count(),
                'confirmados' => $moves()->where('state', 'posted')->count(),
                'do_mes' => $moves()->whereBetween('date', [
                    now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d'),
                ])->count(),
            ],

            'recentes' => Move::where('tenant_id', $tenantId)
                ->where('state', 'posted')
                ->with(['journal:id,name', 'creator:id,name'])
                ->latest('date')->latest('id')->limit(10)->get()
                ->map(fn (Move $m) => [
                    'id' => $m->id,
                    'ref' => $m->ref,
                    'dia' => $m->date?->format('Y-m-d'),
                    'diario' => $m->journal?->name,
                    'autor' => $m->creator?->name,
                    'total' => $protegido(round((float) $m->total_debit, 2)),
                    'nota' => $m->narration,
                ])->values(),

            // Os gráficos são valores: seguem a mesma regra dos saldos.
            'mensal' => $veValores ? $this->movimentoMensal($tenantId) : ['etiquetas' => [], 'valores' => []],
            'contas_movimentadas' => $veValores
                ? $this->contasMaisMovimentadas($tenantId, $de, $ate)
                : ['etiquetas' => [], 'valores' => []],
            // Este conta LANÇAMENTOS, não dinheiro — fica sempre.
            'por_diario' => $this->porDiario($tenantId, $de, $ate),
        ]);
    }

    /** @return array<string,float> natureza => saldo */
    private function saldosPorNatureza(int $tenantId, string $de, string $ate): array
    {
        $linhas = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$de, $ate])
            ->groupBy('c.type')
            ->selectRaw('c.type as tipo, SUM(l.debit) as debito, SUM(l.credit) as credito')
            ->get();

        $saldos = [];

        foreach ($linhas as $linha) {
            $saldos[$linha->tipo] = in_array($linha->tipo, self::A_DEBITO, true)
                ? (float) $linha->debito - (float) $linha->credito
                : (float) $linha->credito - (float) $linha->debito;
        }

        return $saldos;
    }

    /** Débito mês a mês: a forma do ano contabilístico. */
    private function movimentoMensal(int $tenantId): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = DB::table('accounting_moves')
            ->where('tenant_id', $tenantId)
            ->where('state', 'posted')
            ->where('date', '>=', $desde->format('Y-m-d'))
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as mes, SUM(total_debit) as debito")
            ->pluck('debito', 'mes');

        $etiquetas = [];
        $valores = [];

        // OS MESES SEM LANÇAMENTOS APARECEM A ZERO: um gráfico que salta os
        // meses vazios comprime o tempo e mente sobre a tendência.
        for ($m = 0; $m < 12; $m++) {
            $data = $desde->copy()->addMonths($m);

            $etiquetas[] = $data->translatedFormat('M/y');
            $valores[] = round((float) ($porMes[$data->format('Y-m')] ?? 0), 2);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** As contas com mais movimento — onde a contabilidade está a acontecer. */
    private function contasMaisMovimentadas(int $tenantId, string $de, string $ate): array
    {
        $linhas = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$de, $ate])
            ->groupBy('c.id', 'c.code', 'c.name')
            ->selectRaw('CONCAT(c.code, " · ", c.name) as nome, SUM(l.debit + l.credit) as movimento')
            ->orderByDesc('movimento')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->pluck('movimento')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    /** Lançamentos por diário: quem está a alimentar a contabilidade. */
    private function porDiario(int $tenantId, string $de, string $ate): array
    {
        $linhas = DB::table('accounting_moves as m')
            ->leftJoin('accounting_journals as d', 'd.id', '=', 'm.journal_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$de, $ate])
            ->groupBy('d.id', 'd.name')
            ->selectRaw('COALESCE(d.name, "—") as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }
}
