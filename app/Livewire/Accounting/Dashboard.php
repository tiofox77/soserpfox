<?php

namespace App\Livewire\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Move;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public $dateFrom;
    public $dateTo;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    /**
     * Os SALDOS por natureza, e não a contagem de contas.
     *
     * Este painel mostrava "Total Ativo: 42" — quarenta e duas CONTAS de
     * activo. Não é informação nenhuma: um plano de contas tem as contas que
     * tem, e o número não muda com o negócio. O que um contabilista procura
     * ao abrir isto é quanto há em cada natureza, e isso vinha a lugar nenhum.
     *
     * Só os lançamentos LANÇADOS (`posted`) contam. Um rascunho é uma
     * intenção; somá-lo dava um balanço que não existe em lado nenhum.
     */
    private function saldosPorNatureza(int $tenantId): array
    {
        $linhas = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$this->dateFrom, $this->dateTo])
            ->groupBy('c.type')
            ->selectRaw('c.type as tipo, SUM(l.debit) as debito, SUM(l.credit) as credito')
            ->get();

        $saldos = [];

        foreach ($linhas as $linha) {
            // A NATUREZA DECIDE O SINAL. Activo e gasto crescem a débito;
            // passivo, capital e proveito crescem a crédito. Somar tudo da
            // mesma maneira dava proveitos negativos — e um painel que mostra
            // a receita com um sinal de menos não se usa duas vezes.
            $saldos[$linha->tipo] = in_array($linha->tipo, ['asset', 'expense'], true)
                ? (float) $linha->debito - (float) $linha->credito
                : (float) $linha->credito - (float) $linha->debito;
        }

        return $saldos;
    }

    /** Débito e crédito mês a mês: a forma do ano contabilístico. */
    private function movimentoMensal(int $tenantId): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = DB::table('accounting_moves')
            ->where('tenant_id', $tenantId)
            ->where('state', 'posted')
            ->where('date', '>=', $desde)
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as mes, SUM(total_debit) as debito, COUNT(*) as documentos")
            ->pluck('debito', 'mes');

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $data = $desde->copy()->addMonths($m);

            $etiquetas[] = $data->translatedFormat('M/y');
            $valores[] = (float) ($porMes[$data->format('Y-m')] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** As contas com mais movimento — onde a contabilidade está a acontecer. */
    private function contasMaisMovimentadas(int $tenantId): array
    {
        $linhas = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$this->dateFrom, $this->dateTo])
            ->groupBy('c.id', 'c.code', 'c.name')
            ->selectRaw('CONCAT(c.code, " · ", c.name) as nome, SUM(l.debit + l.credit) as movimento')
            ->orderByDesc('movimento')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->pluck('movimento')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /** Lançamentos por diário: quem está a alimentar a contabilidade. */
    private function porDiario(int $tenantId): array
    {
        $linhas = DB::table('accounting_moves as m')
            ->leftJoin('accounting_journals as d', 'd.id', '=', 'm.journal_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$this->dateFrom, $this->dateTo])
            ->groupBy('d.id', 'd.name')
            ->selectRaw('COALESCE(d.name, "—") as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores'   => $linhas->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $saldos = $this->saldosPorNatureza($tenantId);

        $recentMoves = Move::where('tenant_id', $tenantId)
            ->where('state', 'posted')
            ->latest()
            ->take(10)
            ->with(['journal', 'creator'])
            ->get();

        $totalMoves = Move::where('tenant_id', $tenantId)->count();
        $postedMoves = Move::where('tenant_id', $tenantId)->where('state', 'posted')->count();
        $draftMoves = Move::where('tenant_id', $tenantId)->where('state', 'draft')->count();

        return view('livewire.accounting.dashboard.dashboard', [
            // As contagens de contas ficam — servem para saber se o plano está
            // montado. Deixam é de ser o cabeçalho: o cabeçalho são os saldos.
            'totalAssets' => Account::where('tenant_id', $tenantId)->where('type', 'asset')->where('blocked', false)->count(),
            'totalLiabilities' => Account::where('tenant_id', $tenantId)->where('type', 'liability')->where('blocked', false)->count(),
            'totalRevenue' => Account::where('tenant_id', $tenantId)->where('type', 'revenue')->where('blocked', false)->count(),
            'totalExpenses' => Account::where('tenant_id', $tenantId)->where('type', 'expense')->where('blocked', false)->count(),

            'saldoAtivo'    => $saldos['asset'] ?? 0.0,
            'saldoPassivo'  => $saldos['liability'] ?? 0.0,
            'saldoProveito' => $saldos['revenue'] ?? 0.0,
            'saldoGasto'    => $saldos['expense'] ?? 0.0,
            'resultado'     => ($saldos['revenue'] ?? 0.0) - ($saldos['expense'] ?? 0.0),

            'recentMoves' => $recentMoves,
            'totalMoves' => $totalMoves,
            'postedMoves' => $postedMoves,
            'draftMoves' => $draftMoves,

            'mensal'      => $this->movimentoMensal($tenantId),
            'topContas'   => $this->contasMaisMovimentadas($tenantId),
            'porDiario'   => $this->porDiario($tenantId),
        ]);
    }
}
