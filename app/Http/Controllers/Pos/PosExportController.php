<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosExportController extends Controller
{
    /**
     * PDF A4 do resumo de turno (Histórico de Turnos / Fecho de turno).
     */
    public function shiftPdf(int $shiftId)
    {
        $shift = $this->resolveShift($shiftId);
        $tenant = Tenant::find(activeTenantId());

        $pdf = Pdf::loadView('pdf.pos.shift-summary', [
            'shift' => $shift,
            'tenant' => $tenant,
        ]);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'turno-' . $shift->shift_number . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Ticket térmico (80mm) do resumo de turno — HTML com auto-print + close.
     * Quando o utilizador clica no botão "Ticket Térmico" no histórico de turnos,
     * abre numa nova aba que dispara a janela de impressão e fecha-se.
     */
    public function shiftTicket(int $shiftId, Request $request)
    {
        $shift = $this->resolveShift($shiftId);
        $tenant = Tenant::find(activeTenantId());

        // Modo "pdf" mantém comportamento antigo (download/preview PDF)
        if ($request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('pdf.pos.shift-ticket', [
                'shift' => $shift,
                'tenant' => $tenant,
            ]);
            $customPaper = [0, 0, 226, 800]; // 80mm × ~282mm
            $pdf->setPaper($customPaper, 'portrait');
            return $pdf->stream('ticket-turno-' . $shift->shift_number . '.pdf');
        }

        // Modo default: HTML auto-print + auto-close
        return response()->view('pdf.pos.shift-ticket-html', [
            'shift' => $shift,
            'tenant' => $tenant,
            'autoPrint' => $request->query('print', '1') !== '0',
        ]);
    }

    /**
     * Relatório de vendas POS em PDF (A4 landscape para caber colunas).
     */
    public function salesReportPdf(Request $request)
    {
        abort_unless(auth()->user()?->can('invoicing.pos.reports'), 403);

        [$invoices, $filters, $totals] = $this->buildSalesQuery($request);

        $tenant = Tenant::find(activeTenantId());

        $pdf = Pdf::loadView('pdf.pos.sales-report', [
            'invoices' => $invoices->get(),
            'filters' => $filters,
            'totals' => $totals,
            'tenant' => $tenant,
        ]);
        $pdf->setPaper('a4', 'landscape');

        $filename = 'relatorio-vendas-pos-' . now()->format('Ymd-His') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Relatório de vendas POS em XLSX (PhpSpreadsheet).
     */
    public function salesReportExcel(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->can('invoicing.pos.reports'), 403);

        [$invoices, $filters, $totals] = $this->buildSalesQuery($request);

        $rows = $invoices->get();

        $tenant = Tenant::find(activeTenantId());
        // Fonte única (ver AGTHelper): a coluna do tenant fica muitas vezes em
        // C_PENDING e o número real vive na definição global.
        $agtCert = \App\Helpers\AGTHelper::softwareValidationNumber();

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Vendas POS');

        // -------- Cabeçalho com branding SOS ERP + tenant --------
        $sheet->setCellValue('A1', 'SOS ERP — SOLUÇÕES EMPRESARIAIS');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('14532D');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->setCellValue('A2', 'Certificado AGT N.º ' . $agtCert);
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true);

        $sheet->setCellValue('A3', ($tenant->company_name ?? $tenant->name ?? '—') . ' | NIF: ' . ($tenant->nif ?? '—') . ' | ' . ($tenant->address ?? '') . ($tenant->phone ? ' | Tel: ' . $tenant->phone : ''));
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3')->getFont()->setSize(9);

        $sheet->setCellValue('A4', 'RELATÓRIO DE VENDAS POS');
        $sheet->mergeCells('A4:H4');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A5', 'Período: ' . $filters['startDate'] . ' a ' . $filters['endDate'] . '   |   Emitido: ' . now()->format('d/m/Y H:i') . ' por ' . auth()->user()->name);
        $sheet->mergeCells('A5:H5');
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5')->getFont()->setSize(9);

        // -------- Headers tabela (linha 7) --------
        $headers = ['Fatura', 'Data', 'Cliente', 'NIF', 'Pagamento', 'Subtotal', 'IVA', 'Total'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '7', $h);
            $col++;
        }
        $sheet->getStyle('A7:H7')->getFont()->setBold(true);
        $sheet->getStyle('A7:H7')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16A34A');
        $sheet->getStyle('A7:H7')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A7:H7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // -------- Linhas (a partir da 8) --------
        $r = 8;
        foreach ($rows as $inv) {
            $dateForCell = $inv->system_entry_date ?? $inv->invoice_date;
            $sheet->setCellValue('A' . $r, $inv->invoice_number);
            $sheet->setCellValue('B' . $r, optional($dateForCell)->format('d/m/Y H:i'));
            $sheet->setCellValue('C' . $r, optional($inv->client)->name ?? '—');
            $sheet->setCellValue('D' . $r, optional($inv->client)->nif ?? '—');
            $sheet->setCellValue('E' . $r, posPaymentMethodLabel($inv->payment_method));
            $sheet->setCellValue('F' . $r, (float) $inv->subtotal);
            $sheet->setCellValue('G' . $r, (float) $inv->tax_amount);
            $sheet->setCellValue('H' . $r, (float) $inv->total);
            $r++;
        }

        // -------- Totais --------
        $totalRow = $r + 1;
        $sheet->setCellValue('E' . $totalRow, 'TOTAIS');
        $sheet->setCellValue('F' . $totalRow, (float) $totals['subtotal']);
        $sheet->setCellValue('G' . $totalRow, (float) $totals['tax']);
        $sheet->setCellValue('H' . $totalRow, (float) $totals['total']);
        $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0FDF4');

        // Formatação monetária colunas F:H
        $sheet->getStyle('F8:H' . $totalRow)
            ->getNumberFormat()->setFormatCode('#,##0.00');

        // Borders
        $sheet->getStyle('A7:H' . ($r - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Rodapé AGT
        $footRow = $totalRow + 2;
        $sheet->setCellValue('A' . $footRow, 'Processado por programa validado — Certificado AGT N.º ' . $agtCert . ' — Software: SOS ERP — SOLUÇÕES EMPRESARIAIS');
        $sheet->mergeCells('A' . $footRow . ':H' . $footRow);
        $sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $footRow)->getFont()->setSize(8)->setItalic(true);

        // Auto-width
        foreach (range('A', 'H') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'relatorio-vendas-pos-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($ss) {
            $writer = new Xlsx($ss);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ============================== helpers ==============================

    protected function resolveShift(int $shiftId): PosShift
    {
        $shift = PosShift::with(['user', 'closedBy', 'transactions'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($shiftId);

        // Restringir: cada caixa só vê os seus próprios; gestores com pos.reports.all veem todos
        if (!auth()->user()?->can('invoicing.pos.reports.all')) {
            abort_unless((int) $shift->user_id === (int) auth()->id(), 403, 'Acesso negado a este turno.');
        }

        return $shift;
    }

    /**
     * Constrói query partilhada para PDF e Excel.
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array, 2: array}
     */
    protected function buildSalesQuery(Request $request): array
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->format('Y-m-d'));
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $paymentMethod = $request->query('payment_method', '');

        $query = SalesInvoice::with(['client'])
            ->where('tenant_id', activeTenantId())
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if (!auth()->user()?->can('invoicing.pos.reports.all')) {
            $query->where('created_by', auth()->id());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                  ->orWhereHas('client', function ($c) use ($search) {
                      $c->where('name', 'like', '%' . $search . '%')
                        ->orWhere('nif', 'like', '%' . $search . '%');
                  });
            });
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        $query->orderBy('invoice_date', 'desc')->orderBy('id', 'desc');

        // Totais (calc separado para evitar consumo do cursor)
        $totalsQuery = clone $query;
        $totals = [
            'count' => $totalsQuery->count(),
            'subtotal' => (float) $totalsQuery->sum('subtotal'),
            'tax' => (float) $totalsQuery->sum('tax_amount'),
            'discount' => (float) $totalsQuery->sum('discount_amount'),
            'total' => (float) $totalsQuery->sum('total'),
        ];

        $filters = compact('startDate', 'endDate', 'search', 'status', 'paymentMethod');

        return [$query, $filters, $totals];
    }
}
