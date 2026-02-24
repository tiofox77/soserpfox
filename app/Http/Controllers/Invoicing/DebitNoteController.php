<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\DebitNote;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class DebitNoteController extends Controller
{
    public function generatePdf($id)
    {
        try {
            // Buscar ND com relacionamentos
            $debitNote = DebitNote::with(['client', 'invoice', 'items.product', 'creator', 'series'])
                ->where('tenant_id', activeTenantId())
                ->findOrFail($id);
            
            // Buscar dados do tenant
            $tenant = Tenant::find(activeTenantId());
            
            // Buscar contas bancárias para exibir na ND (máximo 4)
            $bankAccounts = \App\Models\Treasury\Account::with('bank')
                ->where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->where('show_on_invoice', true)
                ->orderBy('invoice_display_order')
                ->limit(4)
                ->get();
            
            // Gerar QR Code AGT
            $qrCode = getAGTQRData($debitNote, 80);
            
            // Configurar PDF com options
            $pdf = Pdf::loadView('pdf.invoicing.debit-note', [
                'debitNote' => $debitNote,
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
            $filename = 'nota_debito_' . str_replace(['/', '\\', ' '], '_', $debitNote->debit_note_number) . '.pdf';
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            // Se houver erro, retornar a view HTML diretamente para debug
            return view('pdf.invoicing.debit-note', [
                'debitNote' => DebitNote::with(['client', 'invoice', 'items.product'])
                    ->where('tenant_id', activeTenantId())
                    ->findOrFail($id),
                'tenant' => Tenant::find(activeTenantId()),
                'bankAccounts' => collect(),
            ]);
        }
    }
    
    public function previewHtml($id)
    {
        // Buscar ND com relacionamentos
        $debitNote = DebitNote::with(['client', 'invoice', 'items.product', 'creator', 'series'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        // Buscar dados do tenant
        $tenant = Tenant::find(activeTenantId());
        
        // Buscar contas bancárias para exibir na ND (máximo 4)
        $bankAccounts = \App\Models\Treasury\Account::with('bank')
            ->where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')
            ->limit(4)
            ->get();
        
        // Gerar QR Code AGT
        $qrCode = getAGTQRData($debitNote, 80);
        
        // Retornar view HTML diretamente (sem PDF)
        return view('pdf.invoicing.debit-note', [
            'debitNote' => $debitNote,
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
        ]);
    }
    
    public function previewPaged($id)
    {
        $debitNote = DebitNote::with(['client', 'invoice', 'items.product', 'creator', 'series', 'warehouse'])
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
        
        $qrCode = getAGTQRData($debitNote, 80);
        
        return view('pdf.invoicing.document-paged', [
            'document' => $debitNote,
            'documentType' => 'debit_note',
            'tenant' => $tenant,
            'bankAccounts' => $bankAccounts,
            'qrCode' => $qrCode,
            'pdfRoute' => route('invoicing.debit-notes.pdf', $id),
        ]);
    }
}
