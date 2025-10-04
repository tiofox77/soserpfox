<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesInvoiceController extends Controller
{
    public function generatePdf($id)
    {
        try {
            // Buscar fatura com relacionamentos
            $invoice = SalesInvoice::with(['client', 'items.product', 'warehouse', 'creator'])
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
            
            // Configurar PDF com options
            $pdf = Pdf::loadView('pdf.invoicing.sales-invoice', [
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
            $filename = 'fatura_venda_' . str_replace(['/', '\\', ' '], '_', $invoice->invoice_number) . '.pdf';
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            // Se houver erro, retornar a view HTML diretamente para debug
            return view('pdf.invoicing.sales-invoice', [
                'invoice' => SalesInvoice::with(['client', 'items', 'warehouse'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => Tenant::find(activeTenantId()),
            ]);
        }
    }
    
    public function previewHtml($id)
    {
        // Buscar fatura com relacionamentos
        $invoice = SalesInvoice::with(['client', 'items.product', 'warehouse', 'creator'])
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
        
        // Retornar view HTML diretamente (sem PDF)
        return view('pdf.invoicing.sales-invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
