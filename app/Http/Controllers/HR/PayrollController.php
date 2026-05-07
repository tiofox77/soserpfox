<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayrollItem;
use App\Models\HR\Payroll;

class PayrollController extends Controller
{
    /**
     * Recibo individual — HTML com botão de imprimir
     */
    public function generatePayslipPDF($id)
    {
        $payrollItem = PayrollItem::with(['employee', 'payroll', 'contract'])
            ->findOrFail($id);

        return view('pdf.hr.payslip', compact('payrollItem'));
    }

    /**
     * Recibos de todos os funcionários — HTML com botão de imprimir
     */
    public function generateAllPayslipsPDF($payrollId)
    {
        $payroll = Payroll::with(['items.employee', 'items.contract'])
            ->findOrFail($payrollId);

        $payrollItems = $payroll->items->sortBy(fn($i) => $i->employee->full_name);

        return view('pdf.hr.payslips-all', [
            'payroll' => $payroll,
            'payrollItems' => $payrollItems,
        ]);
    }
}
