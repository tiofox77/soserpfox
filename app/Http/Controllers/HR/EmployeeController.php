<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;

class EmployeeController extends Controller
{
    /**
     * Ficha do trabalhador — HTML com botão de imprimir
     */
    public function employeeSheet($id)
    {
        $employee = Employee::with([
            'department', 'position', 'shift', 'manager',
            'activeContract', 'contracts',
        ])->findOrFail($id);

        return view('pdf.hr.employee-sheet', compact('employee'));
    }
}
