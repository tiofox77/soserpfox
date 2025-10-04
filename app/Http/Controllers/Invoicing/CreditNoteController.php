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
        $creditNote = CreditNote::with(['client', 'invoice', 'items.product', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        $pdf = Pdf::loadView('pdf.invoicing.credit-note', [
            'creditNote' => $creditNote,
            'tenant' => $tenant,
        ]);
        
        $pdf->setPaper('A4', 'portrait');
        
        $filename = 'nota_credito_' . str_replace(['/', '\\'], '_', $creditNote->credit_note_number) . '.pdf';
        return $pdf->stream($filename);
    }
    
    public function previewHtml($id)
    {
        $creditNote = CreditNote::with(['client', 'invoice', 'items.product', 'creator'])
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);
        
        $tenant = Tenant::find(activeTenantId());
        
        return view('pdf.invoicing.credit-note', [
            'creditNote' => $creditNote,
            'tenant' => $tenant,
        ]);
    }
}
