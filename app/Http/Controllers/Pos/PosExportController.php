<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Services\POS\PosSalesReportQuery;
use App\Services\POS\ProdutosDoTurno;
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
    public function shiftPdf(int $shiftId, Request $request)
    {
        $shift = $this->resolveShift($shiftId);
        $tenant = Tenant::find(activeTenantId());
        $produtos = $this->produtosPedidos($shift, $request);

        $pdf = Pdf::loadView('pdf.pos.shift-summary', [
            'shift' => $shift,
            'tenant' => $tenant,
            'produtos' => $produtos,
        ]);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'turno-' . $shift->shift_number . ($produtos ? '-produtos' : '') . '.pdf';

        app(\App\Services\Audit\AuditRecorder::class)
            ->exportou($produtos ? 'fecho de turno com produtos' : 'resumo de turno', 'pdf', null, ['turno' => $shift->shift_number]);

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
        $produtos = $this->produtosPedidos($shift, $request);

        // Modo "pdf" mantém comportamento antigo (download/preview PDF)
        if ($request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('pdf.pos.shift-ticket', [
                'shift' => $shift,
                'tenant' => $tenant,
                'produtos' => $produtos,
            ]);
            // 80mm × ~282mm; com os produtos o rolo cresce com as linhas.
            $altura = 800 + ($produtos ? 34 * count($produtos['produtos']) + 26 * count($produtos['documentos']) + 200 : 0);
            $pdf->setPaper([0, 0, 226, $altura], 'portrait');
            return $pdf->stream('ticket-turno-' . $shift->shift_number . '.pdf');
        }

        // Modo default: HTML auto-print + auto-close
        return response()->view('pdf.pos.shift-ticket-html', [
            'shift' => $shift,
            'tenant' => $tenant,
            'produtos' => $produtos,
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

        app(\App\Services\Audit\AuditRecorder::class)
            ->exportou('relatório de vendas do POS', 'pdf');

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
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('14532D');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->setCellValue('A2', 'Certificado AGT N.º ' . $agtCert);
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true);

        $sheet->setCellValue('A3', ($tenant->company_name ?? $tenant->name ?? '—') . ' | NIF: ' . ($tenant->nif ?? '—') . ' | ' . ($tenant->address ?? '') . ($tenant->phone ? ' | Tel: ' . $tenant->phone : ''));
        $sheet->mergeCells('A3:I3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3')->getFont()->setSize(9);

        $sheet->setCellValue('A4', 'RELATÓRIO DE VENDAS POS');
        $sheet->mergeCells('A4:I4');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A5', 'Período: ' . $filters['startDate'] . ' a ' . $filters['endDate'] . '   |   Emitido: ' . now()->format('d/m/Y H:i') . ' por ' . auth()->user()->name);
        $sheet->mergeCells('A5:I5');
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5')->getFont()->setSize(9);

        // -------- Headers tabela (linha 7) --------
        $headers = ['Tipo', 'Documento', 'Data', 'Cliente', 'NIF', 'Pagamento', 'Subtotal', 'IVA', 'Total'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '7', $h);
            $col++;
        }
        $sheet->getStyle('A7:I7')->getFont()->setBold(true);
        $sheet->getStyle('A7:I7')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16A34A');
        $sheet->getStyle('A7:I7')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A7:I7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // -------- Linhas (a partir da 8) --------
        $r = 8;
        foreach ($rows as $doc) {
            // As notas de crédito saem com sinal NEGATIVO. Na base os dois
            // valores são positivos; sem o sinal, somar a coluna no Excel dava
            // as devoluções a acrescentar às vendas.
            $sinal = $doc->doc_tipo === PosSalesReportQuery::TIPO_NOTA ? -1 : 1;

            $sheet->setCellValue('A' . $r, $doc->doc_tipo);
            $sheet->setCellValue('B' . $r, $doc->numero);
            $sheet->setCellValue('C' . $r, \Carbon\Carbon::parse($doc->data_hora)->format('d/m/Y H:i'));
            $sheet->setCellValue('D' . $r, $doc->cliente_nome ?? '—');
            $sheet->setCellValue('E' . $r, $doc->cliente_nif ?? '—');
            $sheet->setCellValue('F' . $r, $doc->payment_method ? posPaymentMethodLabel($doc->payment_method) : '—');
            $sheet->setCellValue('G' . $r, $sinal * (float) $doc->subtotal);
            $sheet->setCellValue('H' . $r, $sinal * (float) $doc->tax_amount);
            $sheet->setCellValue('I' . $r, $sinal * (float) $doc->total);

            if ($sinal < 0) {
                $sheet->getStyle('A' . $r . ':I' . $r)->getFont()->getColor()->setRGB('B91C1C');
            }

            $r++;
        }

        // -------- Totais --------
        $totalRow = $r + 1;
        $sheet->setCellValue('E' . $totalRow, 'BRUTO');
        $sheet->setCellValue('I' . $totalRow, (float) $totals['bruto']);

        $sheet->setCellValue('E' . ($totalRow + 1), 'DEVOLUÇÕES (NC)');
        $sheet->setCellValue('I' . ($totalRow + 1), -1 * (float) $totals['devolvido']);

        $sheet->setCellValue('E' . ($totalRow + 2), 'LÍQUIDO');
        $sheet->setCellValue('H' . ($totalRow + 2), (float) $totals['imposto']);
        $sheet->setCellValue('I' . ($totalRow + 2), (float) $totals['liquido']);
        $ultimaLinhaTotais = $totalRow + 2;

        $sheet->getStyle('A' . $totalRow . ':I' . $ultimaLinhaTotais)->getFont()->setBold(true);
        $sheet->getStyle('A' . $totalRow . ':I' . $ultimaLinhaTotais)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0FDF4');
        $sheet->getStyle('I' . ($totalRow + 1))->getFont()->getColor()->setRGB('B91C1C');

        // Formatação monetária colunas G:I
        $sheet->getStyle('G8:I' . $ultimaLinhaTotais)
            ->getNumberFormat()->setFormatCode('#,##0.00');

        // Borders
        $sheet->getStyle('A7:I' . ($r - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $totalRow . ':I' . $ultimaLinhaTotais)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Rodapé AGT
        $footRow = $ultimaLinhaTotais + 2;
        $sheet->setCellValue('A' . $footRow, 'Processado por programa validado — Certificado AGT N.º ' . $agtCert . ' — Software: SOS ERP — SOLUÇÕES EMPRESARIAIS');
        $sheet->mergeCells('A' . $footRow . ':I' . $footRow);
        $sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $footRow)->getFont()->setSize(8)->setItalic(true);

        // Auto-width
        foreach (range('A', 'I') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'relatorio-vendas-pos-' . now()->format('Ymd-His') . '.xlsx';

        // Antes do streamDownload: o corpo da resposta corre DEPOIS de o
        // pedido terminar, e lá dentro já não há sessão para resolver a
        // empresa nem transacção onde a linha caiba.
        app(\App\Services\Audit\AuditRecorder::class)
            ->exportou('relatório de vendas do POS', 'excel');

        return response()->streamDownload(function () use ($ss) {
            $writer = new Xlsx($ss);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ============================== helpers ==============================

    /** O fecho com produtos só quando pedido (`?detalhe=produtos`); o resumido é o de sempre. */
    protected function produtosPedidos(PosShift $shift, Request $request): ?array
    {
        return $request->query('detalhe') === 'produtos' ? ProdutosDoTurno::de($shift) : null;
    }

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
        $startDate     = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate       = $request->query('end_date', now()->format('Y-m-d'));
        $search        = $request->query('search', '');
        $status        = $request->query('status', '');
        $paymentMethod = $request->query('payment_method', '');
        $documentType  = $request->query('document_type', '');

        // A MESMA consulta do ecrã. Antes o controller montava a sua versão, e
        // bastou isso para o ficheiro exportado deixar de bater certo com o que
        // se via — as notas de crédito entraram no ecrã e não no ficheiro.
        $consulta = new PosSalesReportQuery(activeTenantId(), [
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'search'         => $search,
            'status'         => $status,
            'payment_method' => $paymentMethod,
            'document_type'  => $documentType,
            'only_user_id'   => auth()->user()?->can('invoicing.pos.reports.all') ? null : auth()->id(),
        ]);

        $filters = compact('startDate', 'endDate', 'search', 'status', 'paymentMethod', 'documentType');

        return [$consulta->listagem(), $filters, $consulta->totais()];
    }
}
