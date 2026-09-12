<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\IncomeStatementFunctionService;
use App\Services\Accounting\IncomeStatementNatureService;
use App\Services\Accounting\Lancamentos;
use App\Services\Accounting\WithholdingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * OS RELATÓRIOS DA CONTABILIDADE — dez mapas, um ecrã.
 *
 * O QUE ESTAVA PARTIDO E CUSTAVA CARO: o `render()` calculava o BALANCETE INTEIRO
 * em TODAS as visitas, qualquer que fosse o relatório escolhido — e calculava-o
 * com um `with(['moveLines' => ...])` sobre TODAS as contas da empresa, somando
 * em PHP. Num plano importado de 1.500 contas com um ano de movimento, abrir o
 * Balanço arrastava o balancete completo à memória para o deitar fora. Aqui cada
 * mapa só se calcula quando é pedido, e o balancete sai de um `group by`.
 *
 * A RAZÃO GERAL tinha o mesmo defeito por outra via: somava o saldo inicial e
 * percorria os movimentos em PHP para acumular. Continua a acumular em PHP —
 * é uma cascata e não há volta — mas parte de duas consultas, não de um `get()`
 * sobre a tabela toda.
 */
class RelatoriosApiController extends Controller
{
    /** Os mapas que este ecrã sabe desenhar. */
    private const MAPAS = [
        'trial_balance', 'ledger', 'journal', 'vat', 'income_statement',
        'balance_sheet', 'income_statement_nature', 'income_statement_function',
        'cash_flow', 'withholding',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.reports.view');

        $tenantId = $this->tenantId();

        return response()->json([
            'mapas' => [
                ['valor' => 'trial_balance', 'rotulo' => __('Balancete de Verificação'), 'icone' => 'fa-scale-balanced',
                    'descricao' => __('Todas as contas com movimento no período, a débito e a crédito.')],
                ['valor' => 'balance_sheet', 'rotulo' => __('Balanço'), 'icone' => 'fa-landmark',
                    'descricao' => __('A posição financeira num dia: activo, passivo e capital próprio.')],
                ['valor' => 'income_statement_nature', 'rotulo' => __('Demonstração de Resultados por Natureza'), 'icone' => 'fa-chart-line',
                    'descricao' => __('Proveitos e gastos por natureza, com as margens.')],
                ['valor' => 'income_statement_function', 'rotulo' => __('Demonstração de Resultados por Funções'), 'icone' => 'fa-sitemap',
                    'descricao' => __('O mesmo resultado, repartido por função — vendas, distribuição, administração.')],
                ['valor' => 'cash_flow', 'rotulo' => __('Demonstração de Fluxos de Caixa'), 'icone' => 'fa-water',
                    'descricao' => __('Por método indirecto: do resultado ao dinheiro que entrou e saiu.')],
                ['valor' => 'income_statement', 'rotulo' => __('Resultados simplificados'), 'icone' => 'fa-receipt',
                    'descricao' => __('Proveitos e gastos agrupados pelos dois primeiros dígitos da conta.')],
                ['valor' => 'ledger', 'rotulo' => __('Razão Geral'), 'icone' => 'fa-book-open',
                    'descricao' => __('O extracto de uma conta, com saldo de abertura e acumulado.')],
                ['valor' => 'journal', 'rotulo' => __('Diário'), 'icone' => 'fa-book',
                    'descricao' => __('Os lançamentos por ordem de data, linha a linha.')],
                ['valor' => 'vat', 'rotulo' => __('Mapa de IVA'), 'icone' => 'fa-percent',
                    'descricao' => __('IVA liquidado e dedutível, e o que se entrega.')],
                ['valor' => 'withholding', 'rotulo' => __('Mapa de Retenções na Fonte'), 'icone' => 'fa-hand-holding-dollar',
                    'descricao' => __('O que se retém a terceiros e se entrega ao Estado.')],
            ],

            'contas' => Account::where('tenant_id', $tenantId)->where('is_view', false)
                ->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->values(),

            'diarios' => Journal::where('tenant_id', $tenantId)->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn ($d) => ['valor' => (string) $d->id, 'rotulo' => $d->code.' · '.$d->name])->values(),

            /*
             * QUE MAPAS EXPORTAM, e para quê. O ecrã antigo mostrava os dois
             * botões em todos e respondia «Tipo de relatório não suporta
             * exportação» depois do clique.
             */
            'exportacoes' => [
                'pdf' => ['balance_sheet', 'income_statement_nature', 'income_statement_function', 'cash_flow', 'withholding'],
                'excel' => ['trial_balance', 'balance_sheet', 'income_statement_nature',
                    'income_statement_function', 'cash_flow', 'vat', 'withholding'],
            ],
        ]);
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.reports.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'mapa' => ['required', Rule::in(self::MAPAS)],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'conta' => ['nullable', 'integer'],
            'diario' => ['nullable', 'integer'],
        ]);

        $de = $filtros['de'] ?? now()->startOfMonth()->format('Y-m-d');
        $ate = $filtros['ate'] ?? now()->format('Y-m-d');

        /*
         * SÓ O MAPA PEDIDO SE CALCULA.
         *
         * O ecrã antigo calculava o balancete em todas as visitas e depois, por
         * cima, o mapa escolhido.
         */
        $dados = match ($filtros['mapa']) {
            'trial_balance' => $this->balancete($tenantId, $de, $ate),
            'ledger' => $this->razao($tenantId, $de, $ate, $filtros['conta'] ?? null),
            'journal' => $this->diario($tenantId, $de, $ate, $filtros['diario'] ?? null),
            'vat' => $this->iva($tenantId, $de, $ate),
            'income_statement' => $this->resultadosSimples($tenantId, $de, $ate),
            'balance_sheet' => app(BalanceSheetService::class)->generate($tenantId, $ate),
            'income_statement_nature' => app(IncomeStatementNatureService::class)->generate($tenantId, $de, $ate),
            'income_statement_function' => app(IncomeStatementFunctionService::class)->generate($tenantId, $de, $ate),
            'cash_flow' => app(CashFlowService::class)->generate($tenantId, $de, $ate),
            'withholding' => app(WithholdingReportService::class)->generate($tenantId, $de, $ate),
        };

        return response()->json([
            'mapa' => $filtros['mapa'],
            'periodo' => ['de' => $de, 'ate' => $ate],
            'data' => $dados,
        ]);
    }

    /**
     * DESCARREGAR UM MAPA em PDF ou em folha de cálculo.
     *
     * NÃO É UMA ROTA DE API: devolve o ficheiro, e por isso vive nas páginas e
     * não no prefixo `react`. O ecrã abre-a numa janela nova.
     *
     * O QUE ESTAVA PARTIDO: o `exportExcel()` do balancete e do mapa de IVA
     * chamava `$this->getTrialBalance()` e `$this->getVatReport()` — dois métodos
     * que NÃO EXISTIAM no componente. «Call to undefined method», nos dois mapas
     * que um contabilista exporta todas as semanas.
     */
    public function descarregar(Request $request, \App\Services\Accounting\ReportExportService $exportador)
    {
        $this->exigir($request, 'accounting.reports.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'mapa' => ['required', Rule::in(self::MAPAS)],
            'formato' => ['required', Rule::in(['pdf', 'excel'])],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $de = $filtros['de'] ?? now()->startOfMonth()->format('Y-m-d');
        $ate = $filtros['ate'] ?? now()->format('Y-m-d');
        $pdf = $filtros['formato'] === 'pdf';

        $ficheiro = match ($filtros['mapa']) {
            'balance_sheet' => $pdf
                ? $exportador->exportBalanceSheetPDF(app(BalanceSheetService::class)->generate($tenantId, $ate), $ate)
                : $exportador->exportBalanceSheetExcel(app(BalanceSheetService::class)->generate($tenantId, $ate), $ate),

            'income_statement_nature' => $pdf
                ? $exportador->exportIncomeNaturePDF(app(IncomeStatementNatureService::class)->generate($tenantId, $de, $ate), $de, $ate)
                : $exportador->exportIncomeNatureExcel(app(IncomeStatementNatureService::class)->generate($tenantId, $de, $ate), $de, $ate),

            'income_statement_function' => $pdf
                ? $exportador->exportIncomeFunctionPDF(app(IncomeStatementFunctionService::class)->generate($tenantId, $de, $ate), $de, $ate)
                : $exportador->exportIncomeFunctionExcel(app(IncomeStatementFunctionService::class)->generate($tenantId, $de, $ate), $de, $ate),

            'cash_flow' => $pdf
                ? $exportador->exportCashFlowPDF(app(CashFlowService::class)->generate($tenantId, $de, $ate), $de, $ate)
                : $exportador->exportCashFlowExcel(app(CashFlowService::class)->generate($tenantId, $de, $ate), $de, $ate),

            'withholding' => $pdf
                ? $exportador->exportWithholdingPDF(app(WithholdingReportService::class)->generate($tenantId, $de, $ate), $de, $ate)
                : $exportador->exportWithholdingExcel(app(WithholdingReportService::class)->generate($tenantId, $de, $ate), $de, $ate),

            // Estes dois só têm folha de cálculo — e é aqui que os dois métodos
            // inexistentes davam «Call to undefined method».
            'trial_balance' => $pdf ? null : $exportador->exportTrialBalanceExcel($this->balancete($tenantId, $de, $ate), $de, $ate),
            'vat' => $pdf ? null : $exportador->exportVatReportExcel($this->iva($tenantId, $de, $ate), $de, $ate),

            default => null,
        };

        abort_if($ficheiro === null, 404, __('Este mapa não se descarrega neste formato.'));

        return $ficheiro;
    }

    /**
     * O BALANCETE, num `group by`.
     *
     * Era `Account::with(['moveLines' => ...])->get()` sobre todas as contas da
     * empresa, com as somas feitas em PHP: mil e quinhentas contas e um ano de
     * movimento davam centenas de milhares de linhas carregadas à memória para
     * somar seis números por conta.
     */
    private function balancete(int $tenantId, string $de, string $ate): array
    {
        $linhas = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->where('c.is_view', false)
            ->whereBetween('m.date', [$de, $ate])
            ->groupBy('c.id', 'c.code', 'c.name', 'c.type')
            ->havingRaw('SUM(l.debit) <> 0 OR SUM(l.credit) <> 0')
            ->orderBy('c.code')
            ->selectRaw('c.id, c.code, c.name, c.type, SUM(l.debit) as debito, SUM(l.credit) as credito')
            ->get();

        $contas = $linhas->map(fn ($l) => [
            'id' => (int) $l->id,
            'codigo' => $l->code,
            'nome' => $l->name,
            'tipo' => $l->type,
            'debito' => round((float) $l->debito, 2),
            'credito' => round((float) $l->credito, 2),
            'saldo' => round((float) $l->debito - (float) $l->credito, 2),
        ]);

        $debito = round($contas->sum('debito'), 2);
        $credito = round($contas->sum('credito'), 2);

        return [
            'contas' => $contas->values(),
            'totais' => [
                'debito' => $debito,
                'credito' => $credito,
                'diferenca' => round($debito - $credito, 2),
                // UM BALANCETE QUE NÃO FECHA é o primeiro sinal de que alguma
                // coisa foi escrita à mão na base.
                'fecha' => abs($debito - $credito) < 0.01,
            ],
        ];
    }

    /** A razão de uma conta — o mesmo cálculo do plano de contas. */
    private function razao(int $tenantId, string $de, string $ate, ?int $contaId): array
    {
        if (! $contaId) {
            return ['conta' => null, 'abertura' => 0, 'linhas' => [], 'totais' => null];
        }

        $conta = Account::where('tenant_id', $tenantId)->find($contaId);

        if (! $conta) {
            return ['conta' => null, 'abertura' => 0, 'linhas' => [], 'totais' => null];
        }

        $aDebito = in_array($conta->type, Lancamentos::A_DEBITO, true);

        $anterior = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->where('l.tenant_id', $tenantId)->where('l.account_id', $contaId)
            ->where('m.state', 'posted')->where('m.date', '<', $de)
            ->selectRaw('COALESCE(SUM(l.debit),0) as debito, COALESCE(SUM(l.credit),0) as credito')
            ->first();

        $abertura = $aDebito
            ? (float) $anterior->debito - (float) $anterior->credito
            : (float) $anterior->credito - (float) $anterior->debito;

        $movimentos = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->leftJoin('accounting_journals as d', 'd.id', '=', 'm.journal_id')
            ->where('l.tenant_id', $tenantId)->where('l.account_id', $contaId)
            ->where('m.state', 'posted')->whereBetween('m.date', [$de, $ate])
            ->orderBy('m.date')->orderBy('m.id')
            ->select(['l.id', 'l.debit', 'l.credit', 'l.narration', 'm.ref', 'm.date', 'd.name as diario'])
            ->get();

        $acumulado = $abertura;
        $linhas = [];

        foreach ($movimentos as $l) {
            $delta = $aDebito
                ? (float) $l->debit - (float) $l->credit
                : (float) $l->credit - (float) $l->debit;

            $acumulado = round($acumulado + $delta, 2);

            $linhas[] = [
                'id' => (int) $l->id,
                'dia' => $l->date,
                'ref' => $l->ref,
                'diario' => $l->diario,
                'nota' => $l->narration,
                'debito' => round((float) $l->debit, 2),
                'credito' => round((float) $l->credit, 2),
                'acumulado' => $acumulado,
            ];
        }

        return [
            'conta' => [
                'codigo' => $conta->code,
                'nome' => $conta->name,
                'cresce_a_debito' => $aDebito,
            ],
            'abertura' => round($abertura, 2),
            'linhas' => $linhas,
            'totais' => [
                'debito' => round(collect($linhas)->sum('debito'), 2),
                'credito' => round(collect($linhas)->sum('credito'), 2),
                'saldo' => round($acumulado, 2),
            ],
        ];
    }

    /** O diário: os lançamentos por ordem de data, com as suas linhas. */
    private function diario(int $tenantId, string $de, string $ate, ?int $diarioId): array
    {
        $lancamentos = Move::where('tenant_id', $tenantId)
            ->where('state', 'posted')
            ->whereBetween('date', [$de, $ate])
            ->when($diarioId, fn ($q) => $q->where('journal_id', $diarioId))
            ->with(['journal:id,name', 'lines.account:id,code,name'])
            ->orderBy('date')->orderBy('id')
            // UM TECTO: o diário de um ano numa empresa a sério são milhares de
            // lançamentos, e um ecrã que os traz todos não abre.
            ->limit(500)
            ->get();

        return [
            'lancamentos' => $lancamentos->map(fn (Move $m) => [
                'id' => $m->id,
                'ref' => $m->ref,
                'dia' => $m->date?->format('Y-m-d'),
                'diario' => $m->journal?->name,
                'nota' => $m->narration,
                'debito' => round((float) $m->total_debit, 2),
                'credito' => round((float) $m->total_credit, 2),
                'linhas' => $m->lines->map(fn ($l) => [
                    'conta' => $l->account ? $l->account->code.' · '.$l->account->name : null,
                    'nota' => $l->narration,
                    'debito' => round((float) $l->debit, 2),
                    'credito' => round((float) $l->credit, 2),
                ])->values(),
            ])->values(),
            'totais' => [
                'lancamentos' => $lancamentos->count(),
                'debito' => round((float) $lancamentos->sum('total_debit'), 2),
                'credito' => round((float) $lancamentos->sum('total_credit'), 2),
            ],
            // Dizer que há mais é melhor do que mostrar 500 como se fossem todos.
            'ha_mais' => $lancamentos->count() >= 500,
        ];
    }

    /**
     * O MAPA DE IVA.
     *
     * As contas resolvem-se pela CHAVE DE INTEGRAÇÃO e não pelo código: é
     * agnóstico ao plano, que é o que permite um plano importado de outro
     * sistema.
     */
    private function iva(int $tenantId, string $de, string $ate): array
    {
        $liquidado = $this->contasComChave($tenantId, ['vat_collected']);
        $dedutivel = $this->contasComChave($tenantId, ['vat_paid']);

        $somar = function (array $ids) use ($tenantId, $de, $ate) {
            if (! $ids) {
                return ['linhas' => collect(), 'debito' => 0.0, 'credito' => 0.0];
            }

            $linhas = DB::table('accounting_move_lines as l')
                ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
                ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
                ->where('l.tenant_id', $tenantId)->whereIn('l.account_id', $ids)
                ->where('m.state', 'posted')->whereBetween('m.date', [$de, $ate])
                ->orderBy('m.date')
                ->select(['m.date', 'm.ref', 'c.code', 'c.name', 'l.debit', 'l.credit', 'l.narration'])
                ->get();

            return [
                'linhas' => $linhas->map(fn ($l) => [
                    'dia' => $l->date,
                    'ref' => $l->ref,
                    'conta' => $l->code.' · '.$l->name,
                    'nota' => $l->narration,
                    'debito' => round((float) $l->debit, 2),
                    'credito' => round((float) $l->credit, 2),
                ])->values(),
                'debito' => round((float) $linhas->sum('debit'), 2),
                'credito' => round((float) $linhas->sum('credit'), 2),
            ];
        };

        $sai = $somar($liquidado);
        $entra = $somar($dedutivel);

        $totalLiquidado = round($sai['credito'] - $sai['debito'], 2);
        $totalDedutivel = round($entra['debito'] - $entra['credito'], 2);

        return [
            'liquidado' => $sai,
            'dedutivel' => $entra,
            'totais' => [
                'liquidado' => $totalLiquidado,
                'dedutivel' => $totalDedutivel,
                'a_entregar' => round($totalLiquidado - $totalDedutivel, 2),
            ],
            /*
             * SEM CONTAS DE IVA MARCADAS não há mapa, e dizê-lo é melhor do que
             * mostrar três zeros: o ecrã antigo devolvia zeros e ninguém sabia
             * se não havia IVA ou se faltava configurar o plano.
             */
            'sem_contas' => ! $liquidado && ! $dedutivel,
        ];
    }

    /** Os ids das contas de movimento ancoradas numa chave de integração. */
    private function contasComChave(int $tenantId, array $chaves): array
    {
        $contas = Account::where('tenant_id', $tenantId)->get(['id', 'code', 'integration_key', 'is_view']);

        $prefixos = $contas->whereIn('integration_key', $chaves)->pluck('code')->filter()->all();

        if (! $prefixos) {
            return [];
        }

        return $contas->filter(function ($c) use ($prefixos) {
            if ($c->is_view) {
                return false;
            }

            foreach ($prefixos as $p) {
                if ($p !== '' && str_starts_with((string) $c->code, (string) $p)) {
                    return true;
                }
            }

            return false;
        })->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    /**
     * OS RESULTADOS SIMPLIFICADOS: proveitos e gastos pelos dois primeiros
     * dígitos da conta.
     *
     * Agrupa por TIPO da conta e não pelo prefixo do código — em PGC-AO a classe
     * 6 são os proveitos, em SNC são os gastos, e um relatório que assuma um dos
     * dois mente no outro plano.
     */
    private function resultadosSimples(int $tenantId, string $de, string $ate): array
    {
        $porTipo = function (string $tipo, bool $aDebito) use ($tenantId, $de, $ate) {
            $linhas = DB::table('accounting_move_lines as l')
                ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
                ->join('accounting_accounts as c', 'c.id', '=', 'l.account_id')
                ->where('l.tenant_id', $tenantId)
                ->where('c.type', $tipo)->where('c.is_view', false)
                ->where('m.state', 'posted')->whereBetween('m.date', [$de, $ate])
                ->groupBy('grupo')
                ->selectRaw('LEFT(c.code, 2) as grupo, MIN(c.name) as nome, SUM(l.debit) as debito, SUM(l.credit) as credito')
                ->orderBy('grupo')
                ->get();

            return $linhas->map(fn ($l) => [
                'grupo' => $l->grupo,
                'nome' => $l->nome,
                'valor' => round($aDebito
                    ? (float) $l->debito - (float) $l->credito
                    : (float) $l->credito - (float) $l->debito, 2),
            ])->values();
        };

        $proveitos = $porTipo('revenue', false);
        $gastos = $porTipo('expense', true);

        $totalProveitos = round($proveitos->sum('valor'), 2);
        $totalGastos = round($gastos->sum('valor'), 2);

        return [
            'proveitos' => $proveitos,
            'gastos' => $gastos,
            'totais' => [
                'proveitos' => $totalProveitos,
                'gastos' => $totalGastos,
                'resultado' => round($totalProveitos - $totalGastos, 2),
            ],
        ];
    }
}
