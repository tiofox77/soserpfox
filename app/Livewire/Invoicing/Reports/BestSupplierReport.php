<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Melhor Fornecedor')]
class BestSupplierReport extends Component
{
    use HasReportFilters;
    
    public function mount() { $this->initFilters('year'); }
    
    public function render()
    {
        $tenantId = activeTenantId();
        $today = Carbon::today();
        
        // Estatísticas por fornecedor
        $stats = DB::table('invoicing_purchase_invoices as inv')
            ->join('invoicing_suppliers as s', 's.id', '=', 'inv.supplier_id')
            ->where('inv.tenant_id', $tenantId)
            ->whereBetween('inv.invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('inv.status', '!=', 'cancelled')
            ->selectRaw('
                s.id, s.name, s.nif,
                COUNT(inv.id) as invoices_count,
                SUM(inv.total) as total_value,
                AVG(inv.total) as avg_ticket,
                SUM(CASE WHEN inv.status = "paid" THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN inv.due_date < ? AND inv.status != "paid" THEN 1 ELSE 0 END) as overdue_count,
                AVG(DATEDIFF(COALESCE(inv.due_date, inv.invoice_date), inv.invoice_date)) as avg_payment_term
            ', [$today])
            ->groupBy('s.id', 's.name', 's.nif')
            ->having('invoices_count', '>=', 1)
            ->get();
        
        // Calcular score (0-100) baseado em múltiplos critérios
        $maxValue = $stats->max('total_value') ?: 1;
        $maxInvoices = $stats->max('invoices_count') ?: 1;
        
        $stats = $stats->map(function ($s) use ($maxValue, $maxInvoices) {
            $volumeScore = ($s->total_value / $maxValue) * 30; // 30 pontos
            $frequencyScore = ($s->invoices_count / $maxInvoices) * 20; // 20 pontos
            $reliabilityScore = $s->invoices_count > 0 ? (($s->invoices_count - $s->overdue_count) / $s->invoices_count) * 25 : 0; // 25 pontos
            $paymentScore = min(25, max(0, ($s->avg_payment_term ?? 0) / 60 * 25)); // 25 pontos (prazo melhor = mais score)
            
            $s->volume_score = round($volumeScore, 1);
            $s->frequency_score = round($frequencyScore, 1);
            $s->reliability_score = round($reliabilityScore, 1);
            $s->payment_score = round($paymentScore, 1);
            $s->total_score = round($volumeScore + $frequencyScore + $reliabilityScore + $paymentScore, 1);
            $s->reliability_pct = $s->invoices_count > 0 ? round((($s->invoices_count - $s->overdue_count) / $s->invoices_count) * 100, 1) : 0;
            
            return $s;
        })->sortByDesc('total_score')->values();
        
        return view('livewire.invoicing.reports.best-supplier-report', compact('stats'));
    }
}
