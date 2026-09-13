<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\SalaryDiscount;
use Barryvdh\DomPDF\Facade\Pdf;

class SalaryDiscountController extends Controller
{
    /**
     * Gerar PDF do desconto salarial
     */
    public function generatePDF($id)
    {
        $discount = SalaryDiscount::with(['employee', 'approvedBy'])->findOrFail($id);
        
        $pdf = Pdf::loadView('hr.pdf.discounts', compact('discount'));
        $pdf->setPaper('a4', 'portrait');
        
        $filename = 'desconto-' . $discount->id . '.pdf';
        
        return $pdf->stream($filename);
    }
}
