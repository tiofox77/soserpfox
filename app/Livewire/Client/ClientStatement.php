<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoicing\SalesInvoice;

#[Layout('layouts.client')]
#[Title('Extrato Financeiro')]
class ClientStatement extends Component
{
    use WithPagination;

    public $periodFilter = 'all'; // all, month, quarter, year
    public $statusFilter = '';

    public function render()
    {
        $client = Auth::guard('client')->user();
        
        // Buscar faturas com filtros
        $invoicesQuery = SalesInvoice::where('client_id', $client->id);
        
        // Aplicar filtro de período
        if ($this->periodFilter === 'month') {
            $invoicesQuery->whereMonth('invoice_date', now()->month)
                         ->whereYear('invoice_date', now()->year);
        } elseif ($this->periodFilter === 'quarter') {
            $invoicesQuery->whereBetween('invoice_date', [
                now()->startOfQuarter(),
                now()->endOfQuarter()
            ]);
        } elseif ($this->periodFilter === 'year') {
            $invoicesQuery->whereYear('invoice_date', now()->year);
        }
        
        // Aplicar filtro de status
        if ($this->statusFilter) {
            $invoicesQuery->where('status', $this->statusFilter);
        }
        
        $invoices = $invoicesQuery->orderBy('invoice_date', 'desc')->paginate(20);

        // Calcular estatísticas financeiras (baseadas no SALDO real, não só no status)
        $allInvoices = SalesInvoice::where('client_id', $client->id)->get();

        // Saldo em aberto de uma fatura = total − pago
        $balanceOf = fn ($inv) => round(($inv->total ?? 0) - ($inv->paid_amount ?? 0), 2);
        // "Em aberto" = tem saldo > 0 e não está paga/cancelada/creditada
        $outstanding = $allInvoices->filter(fn ($inv) =>
            !in_array($inv->status, ['paid', 'cancelled', 'credited'], true) && $balanceOf($inv) > 0.009
        );
        // Atrasadas = em aberto e (status overdue OU vencimento no passado)
        $overdue = $outstanding->filter(fn ($inv) =>
            $inv->status === 'overdue' || ($inv->due_date && $inv->due_date->isPast())
        );

        $stats = [
            // Saldo total em aberto (pendentes + atrasadas + parcialmente pagas)
            'total_pending' => $outstanding->sum($balanceOf),

            // Total efetivamente recebido (faturas pagas + parcelas já pagas das parciais)
            'total_paid' => $allInvoices->where('status', 'paid')->sum('total')
                            + $allInvoices->where('status', 'partially_paid')->sum('paid_amount'),

            // Parcialmente pago
            'partially_paid' => $allInvoices->where('status', 'partially_paid')->sum('total'),
            'partially_paid_amount' => $allInvoices->where('status', 'partially_paid')->sum('paid_amount'),

            // Atrasadas (contagem e saldo)
            'overdue_count' => $overdue->count(),
            'overdue_amount' => $overdue->sum($balanceOf),

            // Total faturado (exclui canceladas e creditadas)
            'total_amount' => $allInvoices->whereNotIn('status', ['cancelled', 'credited'])->sum('total'),

            // Saldo devedor = saldo em aberto
            'balance_due' => $outstanding->sum($balanceOf),
        ];

        // Timeline (últimos 6 meses): recebido vs. em aberto por mês de emissão
        $timeline = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthInvoices = $allInvoices->filter(fn ($inv) =>
                $inv->invoice_date && $inv->invoice_date->month === $month->month
                && $inv->invoice_date->year === $month->year
            );
            $timeline[] = [
                'month' => $month->format('M/Y'),
                'paid' => $monthInvoices->sum(fn ($inv) => $inv->paid_amount ?? 0),
                'pending' => $monthInvoices->filter(fn ($inv) =>
                        !in_array($inv->status, ['paid', 'cancelled', 'credited'], true)
                    )->sum($balanceOf),
            ];
        }

        return view('livewire.client.client-statement', compact('invoices', 'stats', 'timeline'));
    }
}
