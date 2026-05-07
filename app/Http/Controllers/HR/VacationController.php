<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Vacation;

class VacationController extends Controller
{
    /**
     * Comprovativo de férias — HTML com botão de imprimir
     */
    public function generatePDF($id)
    {
        $vacation = Vacation::with([
            'employee.department', 'employee.position',
            'approvedBy', 'replacementEmployee',
        ])->findOrFail($id);

        return view('pdf.hr.vacation', compact('vacation'));
    }
}
