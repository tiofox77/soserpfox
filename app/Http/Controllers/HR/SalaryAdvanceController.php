<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\SalaryAdvance;
use Barryvdh\DomPDF\Facade\Pdf;

class SalaryAdvanceController extends Controller
{
    /**
     * Gerar PDF do adiantamento com logo da empresa
     */
    public function generatePDF($id)
    {
        $advance = SalaryAdvance::with(['employee', 'approvedBy', 'paidBy'])->findOrFail($id);
        
        // Gerar PDF
        $pdf = Pdf::loadView('livewire.hr.advances.pdf', compact('advance'));
        
        // Configurar PDF
        $pdf->setPaper('a4', 'portrait');
        
        // Nome do arquivo
        $filename = 'adiantamento-' . $advance->advance_number . '.pdf';
        
        // Retornar PDF para download
        return $pdf->stream($filename);
    }
}
