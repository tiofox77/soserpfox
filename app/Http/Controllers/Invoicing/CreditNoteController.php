<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\CreditNote;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class CreditNoteController extends Controller
{
    public function generatePdf($id)
    {
        try {
            // Buscar NC com relacionamentos
            $creditNote = CreditNote::with(['client', 'invoice', 'items.product', 'creator', 'series'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);
            
            // Buscar dados do tenant
            $tenant = Tenant::find(activeTenantId());
            
            // Buscar contas bancárias para exibir na NC (máximo 4)
            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();
            
            // Gerar QR Code AGT
            $qrCode = getAGTQRData($creditNote, 80);
            
            // Configurar PDF com options
            $pdf = Pdf::loadView('pdf.invoicing.credit-note', [
                'creditNote' => $creditNote,
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
            $filename = 'nota_credito_' . str_replace(['/', '\\', ' '], '_', $creditNote->credit_note_number) . '.pdf';
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            // Se houver erro, retornar a view HTML diretamente para debug
            return view('pdf.invoicing.credit-note', [
                'creditNote' => CreditNote::with(['client', 'invoice', 'items.product'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => Tenant::find(activeTenantId()),
                'bankAccounts' => collect(),
            ]);
        }
    }
    
    public function previewHtml($id)
    {
        $creditNote = CreditNote::with(['client', 'invoice', 'items.product', 'creator', 'series'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        // Buscar contas bancárias para exibir na NC
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        // Gerar QR Code AGT
        $qrCode = getAGTQRData($creditNote, 80);
        
        return view('pdf.invoicing.credit-note', [
            'creditNote' => $creditNote,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
        ]);
    }
}
