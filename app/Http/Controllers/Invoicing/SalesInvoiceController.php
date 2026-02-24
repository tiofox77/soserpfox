<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesInvoiceController extends Controller
{
    /**
     * Gerar PDF com DomPDF
     */
    public function generatePdf($id)
    {
        $invoice = SalesInvoice::with(['client', 'items.product', 'warehouse', 'creator', 'creditNotes', 'series'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        $qrCode = getAGTQRData($invoice, 80);
        
        $pdf = Pdf::loadView('pdf.invoicing.sales-invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
        ]);
        
        $pdf->setPaper('A4', 'portrait');
        
        $filename = 'fatura_venda_' . str_replace(['/', '\\', ' '], '_', $invoice->invoice_number) . '.pdf';
        return $pdf->stream($filename);
    }
    
    public function previewHtml($id)
    {
        // Buscar fatura com relacionamentos
        $invoice = SalesInvoice::with(['client', 'items.product', 'warehouse', 'creator', 'creditNotes', 'series'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        // Buscar dados do tenant
        $tenant = Tenant::find(activeTenantId());
        
        // Buscar contas bancárias para exibir na fatura (máximo 4)
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        // Gerar QR Code AGT
        $qrCode = getAGTQRData($invoice, 80);
        
        // Retornar view HTML diretamente (sem PDF)
        return view('pdf.invoicing.sales-invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
        ]);
    }
    
    /**
     * Preview HTML Paginado (com header fixo, body paginado, footer dinâmico)
     */
    public function previewPaged($id)
    {
        $invoice = SalesInvoice::with(['client', 'items.product', 'warehouse', 'creator', 'creditNotes', 'series'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        $qrCode = getAGTQRData($invoice, 80);
        
        return view('pdf.invoicing.document-paged', [
            'document' => $invoice,
            'documentType' => 'invoice',
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
            'pdfRoute' => route('invoicing.sales.invoices.pdf', $id),
        ]);
    }
}
