<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Overtime;
use Barryvdh\DomPDF\Facade\Pdf;

class OvertimeController extends Controller
{
    public function generatePDF($id)
    {
        $overtime = Overtime::with(['employee', 'approvedBy', 'rejectedBy', 'paidBy'])
            ->where('tenant_id', auth()->user()->activeTenantId())
            ->findOrFail($id);
        
        $pdf = Pdf::loadView('livewire.hr.overtime.pdf', compact('overtime'));
        $pdf->setPaper('a4', 'portrait');
        
        $filename = 'horas-extras-' . $overtime->overtime_number . '.pdf';
        
        return $pdf->stream($filename);
    }
}
