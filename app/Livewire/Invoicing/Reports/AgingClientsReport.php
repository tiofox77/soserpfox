<?php

namespace App\Livewire\Invoicing\Reports;

use App\Models\Invoicing\SalesInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Carbon\Carbon;

#[Layout('layouts.app')]
#[Title('Aging de Clientes')]
class AgingClientsReport extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();
        $today = Carbon::today();
        
        $invoices = SalesInvoice::with('client')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereRaw('total > COALESCE(paid_amount, 0) + 0.01')
            ->get();
        
        // Agrupar por cliente e faixa
        $grouped = [];
        foreach ($invoices as $inv) {
            $clientId = $inv->client_id;
            $clientName = $inv->client->name ?? 'Cliente removido';
            $balance = $inv->total - ($inv->paid_amount ?? 0);
            
            if (!isset($grouped[$clientId])) {
                $grouped[$clientId] = [
                    'name' => $clientName,
                    'current' => 0,
                    'days_30' => 0,
                    'days_60' => 0,
                    'days_90' => 0,
                    'days_120' => 0,
                    'over_120' => 0,
                    'total' => 0,
                ];
            }
            
            $daysOverdue = $inv->due_date ? $today->diffInDays($inv->due_date, false) : 0;
            $daysOverdue = -$daysOverdue; // positive = overdue
            
            if ($daysOverdue <= 0) $grouped[$clientId]['current'] += $balance;
            elseif ($daysOverdue <= 30) $grouped[$clientId]['days_30'] += $balance;
            elseif ($daysOverdue <= 60) $grouped[$clientId]['days_60'] += $balance;
            elseif ($daysOverdue <= 90) $grouped[$clientId]['days_90'] += $balance;
            elseif ($daysOverdue <= 120) $grouped[$clientId]['days_120'] += $balance;
            else $grouped[$clientId]['over_120'] += $balance;
            
            $grouped[$clientId]['total'] += $balance;
        }
        
        // Sort by total desc
        uasort($grouped, fn($a, $b) => $b['total'] <=> $a['total']);
        
        $bucketTotals = [
            'current' => array_sum(array_column($grouped, 'current')),
            'days_30' => array_sum(array_column($grouped, 'days_30')),
            'days_60' => array_sum(array_column($grouped, 'days_60')),
            'days_90' => array_sum(array_column($grouped, 'days_90')),
            'days_120' => array_sum(array_column($grouped, 'days_120')),
            'over_120' => array_sum(array_column($grouped, 'over_120')),
        ];
        $bucketTotals['grand'] = array_sum($bucketTotals);
        
        return view('livewire.invoicing.reports.aging-clients-report', compact('grouped', 'bucketTotals'));
    }
}
