<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
#[Title('Recebimentos por Meio de Pagamento')]
class PaymentMethodsReport extends Component
{
    use HasReportFilters;

    public $method = '';

    public function mount()
    {
        $this->initFilters('month');
    }

    public static function methodLabel($m): string
    {
        $map = [
            'cash' => 'Numerário', 'numerario' => 'Numerário', 'dinheiro' => 'Numerário',
            'transfer' => 'Transferência', 'transferencia' => 'Transferência', 'bank_transfer' => 'Transferência',
            'multicaixa' => 'Multicaixa', 'tpa' => 'TPA / Multicaixa', 'card' => 'Cartão',
            'cheque' => 'Cheque', 'check' => 'Cheque', 'mobile' => 'Pagamento Móvel',
            'express' => 'Multicaixa Express', 'deposit' => 'Depósito',
        ];
        return $map[strtolower((string) $m)] ?? ($m ? ucfirst((string) $m) : 'Não especificado');
    }

    public function render()
    {
        $tenantId = activeTenantId();

        // Colunas qualificadas (algumas queries fazem join com invoicing_clients, que também tem tenant_id)
        $base = DB::table('invoicing_receipts')
            ->where('invoicing_receipts.tenant_id', $tenantId)
            ->whereBetween('invoicing_receipts.payment_date', [$this->dateFrom, $this->dateTo]);

        if ($this->method !== '') {
            $base->where('invoicing_receipts.payment_method', $this->method);
        }

        // Agregado por meio de pagamento
        $byMethod = (clone $base)
            ->select('payment_method', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(amount_paid) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->payment_method,
                'label' => self::methodLabel($r->payment_method),
                'count' => (int) $r->cnt,
                'total' => (float) $r->total,
            ]);

        $grandTotal = (float) (clone $base)->sum('amount_paid');
        $grandCount = (int) (clone $base)->count();

        // Meios disponíveis para o filtro
        $methods = DB::table('invoicing_receipts')
            ->where('tenant_id', $tenantId)
            ->select('payment_method')->distinct()->pluck('payment_method')
            ->filter()->values();

        // Últimos recebimentos
        $receipts = (clone $base)
            ->leftJoin('invoicing_clients', 'invoicing_receipts.client_id', '=', 'invoicing_clients.id')
            ->select('invoicing_receipts.*', 'invoicing_clients.name as client_name')
            ->orderByDesc('payment_date')
            ->limit(300)
            ->get();

        return view('livewire.invoicing.reports.payment-methods-report', compact(
            'byMethod', 'grandTotal', 'grandCount', 'methods', 'receipts'
        ));
    }
}
