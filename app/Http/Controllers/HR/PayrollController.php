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
        $payrollItem = PayrollItem::with(['employee.department', 'payroll', 'contract'])
            ->findOrFail($id);

        return view('pdf.hr.payslip', compact('payrollItem'));
    }

    /**
     * Recibos de todos os funcionários — HTML com botão de imprimir
     */
    public function generateAllPayslipsPDF($payrollId)
    {
        $payroll = Payroll::with(['items.employee.department', 'items.contract'])
            ->findOrFail($payrollId);

        $payrollItems = $payroll->items->sortBy(fn($i) => $i->employee->full_name);

        return view('pdf.hr.payslips-all', [
            'payroll' => $payroll,
            'payrollItems' => $payrollItems,
        ]);
    }

    public function exportExcel(int $id)
    {
        $payroll = Payroll::with(['items.employee.department'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Folha ' . str_pad((string) $payroll->month, 2, '0', STR_PAD_LEFT));
        $sheet->setCellValue('A1', 'FOLHA DE PAGAMENTO');
        $sheet->setCellValue('A2', $payroll->payroll_number . ' — ' . str_pad((string) $payroll->month, 2, '0', STR_PAD_LEFT) . '/' . $payroll->year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        foreach (['Matrícula', 'Funcionário', 'Departamento', 'Dias', 'Faltas', 'Bruto', 'Subsídios', 'Bónus', 'INSS', 'IRT', 'Deduções', 'Líquido', 'Estado'] as $index => $header) {
            $sheet->setCellValue([$index + 1, 4], $header);
        }
        $sheet->getStyle('A4:M4')->getFont()->setBold(true);

        $row = 5;
        foreach ($payroll->items as $item) {
            foreach ([
                $item->employee?->employee_number ?? '',
                $item->employee?->full_name ?? '',
                $item->employee?->department?->name ?? '',
                (float) $item->worked_days,
                (float) $item->absence_days,
                (float) $item->gross_salary,
                (float) $item->total_allowances,
                (float) $item->total_bonuses,
                (float) $item->inss_employee,
                (float) $item->irt_amount,
                (float) $item->total_deductions,
                (float) $item->net_salary,
                ucfirst((string) $item->status),
            ] as $index => $value) {
                $sheet->setCellValue([$index + 1, $row], $value);
            }
            $row++;
        }

        $sheet->setCellValue("E{$row}", 'TOTAIS');
        foreach (['F' => $payroll->total_gross_salary, 'G' => $payroll->total_allowances, 'H' => $payroll->total_bonuses, 'I' => $payroll->total_inss_employee, 'J' => $payroll->total_irt, 'K' => $payroll->total_deductions, 'L' => $payroll->total_net_salary] as $column => $value) {
            $sheet->setCellValue("{$column}{$row}", (float) $value);
        }
        $sheet->getStyle("E{$row}:L{$row}")->getFont()->setBold(true);
        $sheet->getStyle("F5:L{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'folha_' . $payroll->year . '_' . str_pad((string) $payroll->month, 2, '0', STR_PAD_LEFT) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
