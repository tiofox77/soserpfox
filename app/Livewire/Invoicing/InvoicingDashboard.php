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
    /**
     * O painel desenha o que o serviço contar.
     *
     * As contas saíram daqui para o `PainelDaFacturacao` quando o mesmo
     * painel passou a existir em React: duas cópias das mesmas somas
     * davam números diferentes ao primeiro ajuste.
     */
    public function render()
    {
        $dados = app(\App\Services\Invoicing\PainelDaFacturacao::class)
            ->numeros(activeTenantId());

        return view('livewire.invoicing.invoicing-dashboard', $dados);
    }
}
