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
        
        // Calcular estatísticas financeiras
        $allInvoices = SalesInvoice::where('client_id', $client->id)->get();
        
        $stats = [
            // Total pendente
            'total_pending' => $allInvoices->where('status', 'pending')->sum('total'),
            
            // Total pago
            'total_paid' => $allInvoices->where('status', 'paid')->sum('total'),
            
            // Parcialmente pago
            'partially_paid' => $allInvoices->where('status', 'partially_paid')->sum('total'),
            'partially_paid_amount' => $allInvoices->where('status', 'partially_paid')->sum('paid_amount'),
            
            // Atrasadas
            'overdue_count' => $allInvoices->where('status', 'pending')
                                          ->filter(function($inv) {
                                              return $inv->due_date && $inv->due_date->isPast();
                                          })->count(),
            'overdue_amount' => $allInvoices->where('status', 'pending')
                                           ->filter(function($inv) {
                                               return $inv->due_date && $inv->due_date->isPast();
                                           })->sum('total'),
            
            // Total geral
            'total_amount' => $allInvoices->sum('total'),
            
            // Saldo devedor (pendente + parcialmente pago - valor já pago)
            'balance_due' => $allInvoices->where('status', 'pending')->sum('total') + 
                           ($allInvoices->where('status', 'partially_paid')->sum('total') - 
                            $allInvoices->where('status', 'partially_paid')->sum('paid_amount')),
        ];
        
        // Timeline de pagamentos (últimos 6 meses)
        $timeline = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $timeline[] = [
                'month' => $month->format('M/Y'),
                'paid' => SalesInvoice::where('client_id', $client->id)
                                    ->where('status', 'paid')
                                    ->whereMonth('invoice_date', $month->month)
                                    ->whereYear('invoice_date', $month->year)
                                    ->sum('total'),
                'pending' => SalesInvoice::where('client_id', $client->id)
                                       ->where('status', 'pending')
                                       ->whereMonth('invoice_date', $month->month)
                                       ->whereYear('invoice_date', $month->year)
                                       ->sum('total'),
            ];
        }
        
        return view('livewire.client.client-statement', compact('invoices', 'stats', 'timeline'));
    }
}
