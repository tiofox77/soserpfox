<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayrollItem;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    /**
     * Gerar PDF do recibo de pagamento individual
     */
    public function generatePayslipPDF($id)
    {
        $payrollItem = PayrollItem::with(['employee', 'payroll', 'contract'])
            ->findOrFail($id);
        
        // Gerar PDF
        $pdf = Pdf::loadView('pdf.hr.payslip', compact('payrollItem'));
        
        // Configurar PDF
        $pdf->setPaper('a4', 'portrait');
        
        // Nome do arquivo
        $filename = 'recibo-' . $payrollItem->employee->employee_number . '-' . $payrollItem->payroll->period_start->format('Y-m') . '.pdf';
        
        // Retornar PDF para download
        return $pdf->stream($filename);
    }
}
