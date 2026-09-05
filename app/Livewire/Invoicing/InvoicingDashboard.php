<?php

namespace App\Livewire\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\Advance;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Services\Invoicing\Analytics\GraficosDeFacturacao;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Dashboard - Faturação')]
class InvoicingDashboard extends Component
{
    public $selectedPeriod = 'month'; // week, month, year
    public $chartData = [];

    /**
     * Os restantes graficos do painel. Saem do MESMO servico que alimenta
     * o Relatorio em Graficos: duas consultas escritas a parte acabam
     * sempre por divergir num filtro, e depois o painel diz um total e o
     * relatorio diz outro.
     */
    public array $graficos = [];

    public function updatedSelectedPeriod()
    {
        $this->loadChartData();
    }

    public function mount()
    {
        $this->loadChartData();
    }

    public function loadChartData()
    {
        $tenantId = activeTenantId();
        
        switch ($this->selectedPeriod) {
            case 'week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            default: // month
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
        }

        $analise = GraficosDeFacturacao::para($tenantId, $startDate, $endDate);

        $this->graficos = [
            'estados'        => $analise->estadoDasFacturas(),
            'topProdutos'    => $analise->topProdutos(6),
            'vendasCompras'  => $analise->vendasContraCompras(),
            'meiosPagamento' => $analise->recebimentosPorMeio(),
        ];

        // POR ANO AGRUPA-SE POR MES. Agrupar sempre por dia dava, numa
        // empresa com movimento, uma linha com um ponto por cada dia do ano:
        // ilegivel, e com os rotulos por cima uns dos outros.
        $porMes = $this->selectedPeriod === 'year';

        $chave = $porMes
            ? "DATE_FORMAT(invoice_date, '%Y-%m-01')"
            : 'DATE(invoice_date)';

        $this->chartData = escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw($chave . ' as date, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($linha) use ($porMes) {
                // O rotulo vem daqui: e o servidor que sabe se aquilo e um dia
                // ou um mes, e e ele que tem a lingua da empresa.
                $d = Carbon::parse($linha->date);

                return [
                    'date'   => $linha->date,
                    'total'  => $linha->total,
                    'rotulo' => $porMes
                        ? $d->locale(app()->getLocale())->translatedFormat('M Y')
                        : $d->format('d/m'),
                ];
            })
            ->toArray();
    }

    /**
     * Os estados em que uma factura JÁ NÃO É DÍVIDA.
     *
     * Tudo o resto está por cobrar. Escrever a lista ao contrário — nomear os
     * que contam — foi o que partiu isto: o painel contava `pending` e
     * `partially_paid`, e uma factura `sent` ou `overdue` desaparecia do
     * cartão. Medido nesta base: o cartão dizia 76 mil por cobrar quando havia
     * 15 milhões, e dizia zero vencidas quando havia 14,5 milhões.
     */
    private const LIQUIDADAS = ['cancelled', 'paid', 'credited'];

    /**
     * O que falta receber, pelo SALDO e não pelo total.
     *
     * Uma factura parcialmente paga não deve entrar inteira na dívida: entra o
     * que falta. É a mesma regra do portal do cliente.
     */
    private function porCobrar(int $tenantId, bool $apenasVencidas = false)
    {
        $q = escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereNotIn('status', self::LIQUIDADAS)
            ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) > 0.01');

        if ($apenasVencidas) {
            $q->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today());
        }

        return $q;
    }

    private function somaPorCobrar(int $tenantId, bool $apenasVencidas = false): float
    {
        return (float) $this->porCobrar($tenantId, $apenasVencidas)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(total, 0) - COALESCE(paid_amount, 0)'));
    }

    /** O que se facturou num intervalo, sem contar o que foi anulado. */
    private function facturadoEntre(int $tenantId, Carbon $de, Carbon $ate): float
    {
        return (float) escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum('total');
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Estatísticas principais
        $stats = [
            // Faturação
            'total_invoiced' => escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
                ->whereMonth('invoice_date', $today->month)
                ->whereYear('invoice_date', $today->year)
                ->where('status', '!=', 'cancelled')
                ->sum('total'),
            
            'total_invoiced_last_month' => escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
                ->whereBetween('invoice_date', [$lastMonth, $lastMonthEnd])
                ->where('status', '!=', 'cancelled')
                ->sum('total'),

            // Recebimentos
            'total_received' => escopoDoAutor(Receipt::where('tenant_id', $tenantId))
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year)
                ->where('status', 'issued')
                ->sum('amount_paid'),

            // Por cobrar e vencidas: pela regra única, com o saldo.
            'total_pending' => $this->somaPorCobrar($tenantId),

            'total_overdue' => $this->somaPorCobrar($tenantId, true),

            // ANO A ANO, ate ao mesmo dia. Comparar um ano a meio com um ano
            // inteiro daria sempre uma queda que nao existe.
            'year_invoiced' => $this->facturadoEntre(
                $tenantId,
                $today->copy()->startOfYear(),
                $today->copy()->endOfDay()
            ),

            'year_invoiced_previous' => $this->facturadoEntre(
                $tenantId,
                $today->copy()->subYear()->startOfYear(),
                $today->copy()->subYear()->endOfDay()
            ),
        ];

        // Calcular crescimento
        $stats['growth'] = $stats['total_invoiced_last_month'] > 0
            ? (($stats['total_invoiced'] - $stats['total_invoiced_last_month']) / $stats['total_invoiced_last_month']) * 100
            : 0;

        $stats['year_growth'] = $stats['year_invoiced_previous'] > 0
            ? (($stats['year_invoiced'] - $stats['year_invoiced_previous']) / $stats['year_invoiced_previous']) * 100
            : 0;

        // Documentos por tipo
        $documents = [
            'invoices' => escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
                ->whereMonth('invoice_date', $today->month)
                ->whereYear('invoice_date', $today->year)
                ->count(),
            
            'credit_notes' => escopoDoAutor(CreditNote::where('tenant_id', $tenantId))
                ->whereMonth('issue_date', $today->month)
                ->whereYear('issue_date', $today->year)
                ->count(),
            
            'debit_notes' => escopoDoAutor(DebitNote::where('tenant_id', $tenantId))
                ->whereMonth('issue_date', $today->month)
                ->whereYear('issue_date', $today->year)
                ->count(),
            
            'receipts' => escopoDoAutor(Receipt::where('tenant_id', $tenantId))
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year)
                ->count(),
            
            'advances' => escopoDoAutor(Advance::where('tenant_id', $tenantId))
                ->whereMonth('payment_date', $today->month)
                ->whereYear('payment_date', $today->year)
                ->count(),
        ];

        // Faturas pendentes (top 10)
        // A mesma regra do cartão: a lista e o número têm de bater certo.
        $pendingInvoices = $this->porCobrar($tenantId)
            ->with('client')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(10)
            ->get();

        // Top 5 clientes por valor
        $topClients = escopoDoAutor(SalesInvoice::with('client')
            ->where('tenant_id', $tenantId))
            ->whereMonth('invoice_date', $today->month)
            ->whereYear('invoice_date', $today->year)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('client_id, SUM(total) as total_amount, COUNT(*) as invoice_count')
            ->groupBy('client_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        // Atividades recentes
        $recentActivities = escopoDoAutor(SalesInvoice::with('client')
            ->where('tenant_id', $tenantId))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // ESTADO DAS FACTURAS — as quatro caixas do mes.
        //
        // O mes E o ano: sem o ano, Setembro de 2026 contava tambem Setembro de
        // 2025 e de todos os anos anteriores.
        //
        // E contam-se pelo saldo, como os cartoes, para os quatro numeros nao
        // dizerem uma coisa e o cartao dizer outra. Nao se sobrepoem: uma
        // factura cai numa caixa e numa so.
        $doMes = fn ($q) => $q->whereMonth('invoice_date', $today->month)
                              ->whereYear('invoice_date', $today->year);

        $porVencer = fn ($q) => $q->where(function ($w) use ($today) {
            $w->whereNull('due_date')->orWhereDate('due_date', '>=', $today);
        });

        $invoiceStatus = [
            // Liquidadas por pagamento: o avesso do "por cobrar".
            'paid' => $doMes(
                escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
                    ->whereNotIn('status', ['cancelled', 'credited'])
                    ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) <= 0.01')
            )->count(),

            // Por cobrar, dentro do prazo e ainda sem nada recebido.
            'pending' => $porVencer($doMes($this->porCobrar($tenantId)))
                ->whereRaw('COALESCE(paid_amount, 0) <= 0.01')
                ->count(),

            // Por cobrar, dentro do prazo, com parte ja recebida.
            'partially_paid' => $porVencer($doMes($this->porCobrar($tenantId)))
                ->whereRaw('COALESCE(paid_amount, 0) > 0.01')
                ->count(),

            // Passou da data e falta receber — seja qual for o nome do estado.
            'overdue' => $doMes($this->porCobrar($tenantId, true))->count(),
        ];

        return view('livewire.invoicing.invoicing-dashboard', [
            'stats' => $stats,
            'documents' => $documents,
            'pendingInvoices' => $pendingInvoices,
            'topClients' => $topClients,
            'recentActivities' => $recentActivities,
            'invoiceStatus' => $invoiceStatus,
        ]);
    }
}
