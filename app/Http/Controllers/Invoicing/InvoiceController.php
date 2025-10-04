<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function generatePdf($id)
    {
        try {
            // Buscar fatura com relacionamentos
            $invoice = \App\Models\Invoicing\SalesInvoice::with(['client', 'items.product', 'warehouse', 'user', 'creator'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);
            
            // Buscar dados do tenant
            $tenant = \App\Models\Tenant::find(activeTenantId());
            
            // Buscar contas bancárias para exibir na fatura (máximo 4)
            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();
            
            // Configurar PDF com options
            $pdf = Pdf::loadView('pdf.invoicing.invoice', [
                'invoice' => $invoice,
                'tenant' => $tenant,
                'bankAccounts' => $bankAccounts,
            ]);
            
            // Configurar tamanho A4 e orientação
            $pdf->setPaper('A4', 'portrait');
            
            // Configurar opções do DomPDF
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);
            
            // Retornar PDF para visualização no navegador
            $filename = 'fatura_' . str_replace(['/', '\\', ' '], '_', $invoice->invoice_number) . '.pdf';
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            // Se houver erro, retornar a view HTML diretamente para debug
            return view('pdf.invoicing.invoice', [
                'invoice' => \App\Models\Invoicing\SalesInvoice::with(['client', 'items', 'warehouse', 'user'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => \App\Models\Tenant::find(activeTenantId()),
            ]);
        }
    }
    
    public function downloadPdf($id)
    {
        // Buscar fatura com relacionamentos
        $invoice = \App\Models\Invoicing\SalesInvoice::with(['client', 'items', 'warehouse', 'user'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        // Buscar dados do tenant
        $tenant = \App\Models\Tenant::find(activeTenantId());
        
        // Configurar PDF
        $pdf = Pdf::loadView('pdf.invoicing.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
        ]);
        
        // Configurar tamanho A4 e orientação
        $pdf->setPaper('A4', 'portrait');
        
        // Forçar download
        $filename = 'fatura_' . str_replace(['/', '\\', ' '], '_', $invoice->invoice_number) . '.pdf';
        return $pdf->download($filename);
    }
    
    public function previewHtml($id)
    {
        // Buscar fatura com relacionamentos
        $invoice = \App\Models\Invoicing\SalesInvoice::with(['client', 'items.product', 'warehouse', 'user', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        // Buscar dados do tenant
        $tenant = \App\Models\Tenant::find(activeTenantId());
        
        // Buscar contas bancárias para exibir na fatura (máximo 4)
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        // Retornar view HTML diretamente (sem PDF)
        return view('pdf.invoicing.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
