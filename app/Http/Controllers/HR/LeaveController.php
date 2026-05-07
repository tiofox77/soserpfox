<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Leave;

class LeaveController extends Controller
{
    /**
     * Comprovativo de licença/falta — HTML com botão de imprimir
     */
    public function generatePDF($id)
    {
        $leave = Leave::with([
            'employee.department', 'employee.position',
            'approvedBy',
        ])->findOrFail($id);

        return view('pdf.hr.leave', compact('leave'));
    }
}
