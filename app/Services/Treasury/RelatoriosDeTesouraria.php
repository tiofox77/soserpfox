<?php

namespace App\Services\Treasury;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * As contas dos relatórios financeiros, num sítio só.
 *
 * Estavam dentro do componente Livewire, o que chegava enquanto só o ecrã as
 * usava. A partir do momento em que há PDF e Excel, ter as consultas lá dentro
 * obrigava a copiá-las para o controlador — e um relatório que dá números
 * diferentes no ecrã e no PDF é pior do que não ter relatório nenhum: alguém
 * imprime, leva a uma reunião, e descobre em público que não bate certo.
 *
 * O tenant vem sempre por argumento, nunca de `activeTenantId()`: isto também
 * corre a partir de um controlador de descarga, onde o contexto pode não ser o
 * que se julga.
 */
class RelatoriosDeTesouraria
{
    /** Os relatórios que existem, e como se chamam. */
    public const TIPOS = [
        'cash_flow'   => 'Fluxo de Caixa',
        'dre'         => 'Demonstração de Resultados',
        'receivables' => 'Contas a Receber',
        'payables'    => 'Contas a Pagar',
    ];

    public function __construct(
        private int $tenantId,
        private string $de,
        private string $ate,
    ) {
    }

    public static function nomeDoTipo(string $tipo): string
    {
        return self::TIPOS[$tipo] ?? 'Relatório';
    }

    /** Os dados de um relatório, pela chave. */
    public function dados(string $tipo): array
    {
        return match ($tipo) {
            'cash_flow'   => $this->fluxoDeCaixa(),
            'dre'         => $this->demonstracaoDeResultados(),
            'receivables' => $this->contasAReceber(),
            'payables'    => $this->contasAPagar(),
            default       => [],
        };
    }

    // ── fluxo de caixa ───────────────────────────────────────────────────────

    public function fluxoDeCaixa(): array
    {
        $saldoInicial = Transaction::where('tenant_id', $this->tenantId)
            ->where('status', 'completed')
            ->where('transaction_date', '<', $this->de)
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance")
            ->value('balance') ?? 0;

        $entradas = $this->porCategoria('income');
        $saidas = $this->porCategoria('expense');

        $totalEntradas = $entradas->sum('total');
        $totalSaidas = $saidas->sum('total');

        return [
            'initialBalance'    => $saldoInicial,
            'incomeByCategory'  => $entradas,
            'totalIncome'       => $totalEntradas,
            'expenseByCategory' => $saidas,
            'totalExpense'      => $totalSaidas,
            'finalBalance'      => $saldoInicial + $totalEntradas - $totalSaidas,
        ];
    }

    private function porCategoria(string $tipo)
    {
        return Transaction::where('tenant_id', $this->tenantId)
            ->where('type', $tipo)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$this->de, $this->ate])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    // ── demonstração de resultados ───────────────────────────────────────────

    public function demonstracaoDeResultados(): array
    {
        $receitaBruta = SalesInvoice::where('tenant_id', $this->tenantId)
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total');

        // Devoluções e descontos. Continua por implementar contra as notas de
        // crédito — fica a zero e é dito no relatório, em vez de se inventar
        // um número que ninguém consegue justificar.
        $deducoes = 0;

        $receitaLiquida = $receitaBruta - $deducoes;

        $custosOperacionais = PurchaseInvoice::where('tenant_id', $this->tenantId)
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total');

        $despesasPorCategoria = Transaction::where('tenant_id', $this->tenantId)
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$this->de, $this->ate])
            ->whereNotNull('category')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $totalDespesas = $despesasPorCategoria->sum('total');
        $lucroBruto = $receitaLiquida - $custosOperacionais;
        $lucroOperacional = $lucroBruto - $totalDespesas;

        return [
            'grossRevenue'        => $receitaBruta,
            'deductions'          => $deducoes,
            'netRevenue'          => $receitaLiquida,
            'operationalCosts'    => $custosOperacionais,
            'grossProfit'         => $lucroBruto,
            'expensesByCategory'  => $despesasPorCategoria,
            'totalExpenses'       => $totalDespesas,
            'operationalProfit'   => $lucroOperacional,
            // Sem impostos: é uma aproximação, e o relatório di-lo.
            'netProfit'           => $lucroOperacional,
        ];
    }

    // ── contas a receber e a pagar ───────────────────────────────────────────

    public function contasAReceber(): array
    {
        $linhas = SalesInvoice::where('tenant_id', $this->tenantId)
            ->with('client')
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($f) => $this->linhaEmAberto($f, 'client'));

        return [
            'receivables'      => $linhas,
            'totalReceivables' => $linhas->sum('balance'),
            'totalOverdue'     => $linhas->where('overdue', true)->sum('balance'),
        ];
    }

    public function contasAPagar(): array
    {
        $linhas = PurchaseInvoice::where('tenant_id', $this->tenantId)
            ->with('supplier')
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereBetween('invoice_date', [$this->de, $this->ate])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($f) => $this->linhaEmAberto($f, 'supplier'));

        return [
            'payables'      => $linhas,
            'totalPayables' => $linhas->sum('balance'),
            'totalOverdue'  => $linhas->where('overdue', true)->sum('balance'),
        ];
    }

    /** A mesma linha para clientes e fornecedores — só muda de quem é. */
    private function linhaEmAberto($factura, string $quem): array
    {
        return [
            'invoice_number' => $factura->invoice_number,
            $quem            => $factura->{$quem}->name ?? '—',
            'invoice_date'   => $factura->invoice_date,
            'due_date'       => $factura->due_date,
            'total'          => $factura->total,
            'paid'           => $factura->paid_amount ?? 0,
            'balance'        => $factura->balance,
            'status'         => $factura->status,
            'overdue'        => $factura->due_date && $factura->due_date->isPast(),
        ];
    }
}
