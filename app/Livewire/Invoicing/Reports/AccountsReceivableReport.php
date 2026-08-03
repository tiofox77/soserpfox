<?php

namespace App\Livewire\Invoicing\Reports;

use App\Models\Invoicing\SalesInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Contas a Receber')]
class AccountsReceivableReport extends Component
{
    public $statusFilter = 'open';
    
    public function render()
    {
        $tenantId = activeTenantId();
        $today = Carbon::today();
        
        // Considera todas as faturas com saldo em aberto, excepto pagas/canceladas
        $query = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereRaw('total > COALESCE(paid_amount, 0) + 0.01'); // tolerância de arredondamento
        
        if ($this->statusFilter === 'overdue') {
            $query->where('due_date', '<', $today);
        } elseif ($this->statusFilter === 'current') {
            $query->where('due_date', '>=', $today);
        }
        
        $invoices = (clone $query)->orderBy('due_date')->limit(500)->get();
        
        $totals = [
            'total' => $invoices->sum('total'),
            'paid' => $invoices->sum('paid_amount'),
        ];
        $totals['balance'] = $totals['total'] - $totals['paid'];
        
        // Overdue analysis
        $overdueRows = $invoices->filter(fn($i) => $i->due_date && $i->due_date->lt($today));
        $overdueTotal = $overdueRows->sum(fn($i) => $i->total - ($i->paid_amount ?? 0));
        
        return view('livewire.invoicing.reports.accounts-receivable-report', compact('invoices', 'totals', 'overdueTotal', 'today'));
    }
}
