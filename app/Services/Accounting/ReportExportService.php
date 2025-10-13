<?php

namespace App\Services\Accounting;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Accounting\BalanceSheetExport;
use App\Exports\Accounting\IncomeStatementNatureExport;
use App\Exports\Accounting\IncomeStatementFunctionExport;
use App\Exports\Accounting\CashFlowExport;
use App\Exports\Accounting\TrialBalanceExport;
use App\Exports\Accounting\LedgerExport;
use App\Exports\Accounting\JournalExport;
use App\Exports\Accounting\VatReportExport;

class ReportExportService
{
    /**
     * Exporta Balanço para PDF
     */
    public function exportBalanceSheetPDF($data, $date)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.balance-sheet', [
            'data' => $data,
            'date' => $date,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('balanco_' . date('Y-m-d', strtotime($date)) . '.pdf');
    }
    
    /**
     * Exporta DR por Natureza para PDF
     */
    public function exportIncomeNaturePDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.income-nature', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('dr_natureza_' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Exporta DR por Funções para PDF
     */
    public function exportIncomeFunctionPDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.income-function', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('dr_funcoes_' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Exporta Fluxos de Caixa para PDF
     */
    public function exportCashFlowPDF($data, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('accounting.exports.pdf.cash-flow', [
            'data' => $data,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'company' => $this->getCompanyInfo(),
        ]);
        
        return $pdf->download('fluxos_caixa_' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Exporta Balancete para Excel
     */
    public function exportTrialBalanceExcel($data, $dateFrom, $dateTo)
    {
        return Excel::download(
            new TrialBalanceExport($data, $dateFrom, $dateTo),
            'balancete_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exporta Balanço para Excel
     */
    public function exportBalanceSheetExcel($data, $date)
    {
        return Excel::download(
            new BalanceSheetExport($data, $date),
            'balanco_' . date('Y-m-d', strtotime($date)) . '.xlsx'
        );
    }
    
    /**
     * Exporta DR por Natureza para Excel
     */
    public function exportIncomeNatureExcel($data, $dateFrom, $dateTo)
    {
        return Excel::download(
            new IncomeStatementNatureExport($data, $dateFrom, $dateTo),
            'dr_natureza_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exporta DR por Funções para Excel
     */
    public function exportIncomeFunctionExcel($data, $dateFrom, $dateTo)
    {
        return Excel::download(
            new IncomeStatementFunctionExport($data, $dateFrom, $dateTo),
            'dr_funcoes_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exporta Fluxos de Caixa para Excel
     */
    public function exportCashFlowExcel($data, $dateFrom, $dateTo)
    {
        return Excel::download(
            new CashFlowExport($data, $dateFrom, $dateTo),
            'fluxos_caixa_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Exporta Mapa IVA para Excel
     */
    public function exportVatReportExcel($data, $dateFrom, $dateTo)
    {
        return Excel::download(
            new VatReportExport($data, $dateFrom, $dateTo),
            'mapa_iva_' . date('Y-m-d') . '.xlsx'
        );
    }
    
    /**
     * Obtém informações da empresa
     */
    protected function getCompanyInfo()
    {
        $tenant = auth()->user()->tenant;
        
        return [
            'name' => $tenant->name ?? 'Empresa',
            'nif' => $tenant->nif ?? '',
            'address' => $tenant->address ?? '',
            'city' => $tenant->city ?? 'Luanda',
            'country' => 'Angola',
        ];
    }
    
    /**
     * Formata valor em Kwanzas
     */
    public function formatKz($value)
    {
        return number_format($value, 2, ',', '.') . ' Kz';
    }
    
    /**
     * Formata data em português
     */
    public function formatDate($date)
    {
        return date('d/m/Y', strtotime($date));
    }
}
