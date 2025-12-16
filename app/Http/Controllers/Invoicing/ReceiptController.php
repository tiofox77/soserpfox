<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Receipt;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function generatePdf($id)
    {
        try {
            // Buscar recibo com relacionamentos
            $receipt = Receipt::with(['client', 'supplier', 'invoice', 'creator', 'series'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);
            
            // Buscar dados do tenant
            $tenant = Tenant::find(activeTenantId());
            
            // Buscar contas bancárias para exibir no recibo (máximo 4)
            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();
            
            // Gerar QR Code AGT
            $qrCode = getAGTQRData($receipt, 80);
            
            // Configurar PDF com options
            $pdf = Pdf::loadView('pdf.invoicing.receipt', [
                'receipt' => $receipt,
                'tenant' => $tenant,
                'bankAccounts' => $bankAccounts,
                'qrCode' => $qrCode,
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
            $filename = 'recibo_' . str_replace(['/', '\\', ' '], '_', $receipt->receipt_number) . '.pdf';
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            // Se houver erro, retornar a view HTML diretamente para debug
            return view('pdf.invoicing.receipt', [
                'receipt' => Receipt::with(['client', 'supplier', 'invoice'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => Tenant::find(activeTenantId()),
                'bankAccounts' => collect(),
            ]);
        }
    }
    
    public function previewHtml($id)
    {
        // Buscar recibo com relacionamentos
        $receipt = Receipt::with(['client', 'supplier', 'invoice', 'creator', 'series'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        // Buscar dados do tenant
        $tenant = Tenant::find(activeTenantId());
        
        // Buscar contas bancárias para exibir no recibo (máximo 4)
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        // Gerar QR Code AGT
        $qrCode = getAGTQRData($receipt, 80);
        
        // Retornar view HTML diretamente (sem PDF)
        return view('pdf.invoicing.receipt', [
            'receipt' => $receipt,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
        ]);
    }
}
